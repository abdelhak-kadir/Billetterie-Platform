<?php

declare(strict_types=1);
namespace App\Tests\Unit;
use App\Sheets\{AccessTokenProvider,GoogleSheetsClient,GoogleTokenProvider,HttpTransport,SaleProjection};
use PHPUnit\Framework\TestCase;

final class GoogleSheetsClientTest extends TestCase
{
    public function testRetryWritesIdenticalRangeUsingRawValuesWithoutAppend(): void
    {
        $calls=[];$responses=[self::metadata(),['values'=>[]],[],['values'=>[]],[],['values'=>[['TICKET-1']]],[]];
        $client=$this->client($responses,$calls);
        $values=array_fill(0,16,'');$values[0]='TICKET-1';$values[2]='=1+1';
        $client->write(42,$values);$client->write(42,$values);
        $puts=array_values(array_filter($calls,fn($call)=>$call[0]==='PUT'));
        self::assertCount(3,$puts);
        self::assertSame($puts[1][1],$puts[2][1]);
        self::assertStringContainsString('valueInputOption=RAW',$puts[1][1]);
        self::assertStringNotContainsString('append',implode('\n',array_column($calls,1)));
        self::assertSame('=1+1',json_decode($puts[1][2],true)['values'][0][2]);
    }

    public function testForeignRowIsNotOverwritten(): void
    {
        $calls=[];$responses=[self::metadata(),['values'=>[SaleProjection::COLUMNS]],[],['values'=>[['OTHER-TICKET']]]];
        $client=$this->client($responses,$calls);
        try {$client->write(42,array_fill(0,16,'TICKET-1'));self::fail('Un déplacement doit être signalé.');}
        catch (\RuntimeException $e) {self::assertStringContainsString('déplacées',$e->getMessage());}
        self::assertCount(1,array_filter($calls,fn($call)=>$call[0]==='PUT'));
    }

    public function testForeignHeadersAreRefusedWithoutWriting(): void
    {
        $calls=[];$responses=[self::metadata(),['values'=>[['Other format']]]];
        $client=$this->client($responses,$calls);
        try {$client->write(42,array_fill(0,16,''));self::fail('Un autre format doit être refusé.');}
        catch (\RuntimeException $e) {self::assertStringContainsString('autre format',$e->getMessage());}
        self::assertCount(0,array_filter($calls,fn($call)=>$call[0]==='PUT'));
    }

    public function testUnauthorizedResponseInvalidatesTokenAndRedactsBody(): void
    {
        $token=$this->createMock(AccessTokenProvider::class);$token->method('token')->willReturn('fictitious-token');
        $token->expects(self::once())->method('invalidate');
        $http=$this->createMock(HttpTransport::class);
        $http->method('request')->willReturn(['status'=>401,'body'=>'private-response-marker']);
        $client=new GoogleSheetsClient($token,$http,'test-spreadsheet','Ventes Horizon');
        try {$client->write(2,array_fill(0,16,''));self::fail('Une erreur API doit remonter.');}
        catch (\RuntimeException $e) {self::assertStringContainsString('401',$e->getMessage());self::assertStringNotContainsString('private-response-marker',$e->getMessage());}
    }

    public function testMissingTabIsCreatedWithEnoughRows(): void
    {
        $calls=[];$tab=self::metadata()['sheets'][0]['properties'];$tab['gridProperties']['rowCount']=5100;
        $responses=[['sheets'=>[]],['replies'=>[['addSheet'=>['properties'=>$tab]]]],['values'=>[]],[],['values'=>[]],[]];
        $client=$this->client($responses,$calls);$client->write(5000,array_fill(0,16,''));
        $request=json_decode($calls[1][2],true)['requests'][0]['addSheet']['properties'];
        self::assertGreaterThanOrEqual(5000,$request['gridProperties']['rowCount']);
        self::assertSame(16,$request['gridProperties']['columnCount']);
    }

    public function testGridIsExpandedBeforeWritingBeyondItsBounds(): void
    {
        $calls=[];$meta=self::metadata();$meta['sheets'][0]['properties']['gridProperties']['rowCount']=10;
        $responses=[$meta,[],['values'=>[]],[],['values'=>[]],[]];
        $client=$this->client($responses,$calls);$client->write(300,array_fill(0,16,''));
        $request=json_decode($calls[1][2],true)['requests'][0]['updateSheetProperties'];
        self::assertSame(7,$request['properties']['sheetId']);
        self::assertGreaterThanOrEqual(300,$request['properties']['gridProperties']['rowCount']);
    }

    public function testMissingCredentialFileDoesNotPublishItsPath(): void
    {
        $path='/tmp/horizon-missing-test-credential-file.json';
        try {(new GoogleTokenProvider($path))->token();self::fail('Clé absente.');}
        catch (\RuntimeException $e) {self::assertStringNotContainsString($path,$e->getMessage());}
    }

    private function client(array $responses,array &$calls): GoogleSheetsClient
    {
        $token=$this->createMock(AccessTokenProvider::class);$token->method('token')->willReturn('fictitious-token');
        $http=$this->createMock(HttpTransport::class);
        $http->method('request')->willReturnCallback(static function($method,$url,$headers,$body)use(&$responses,&$calls):array {
            $calls[]=[$method,$url,$body];
            self::assertNotEmpty($responses,'Appel HTTP inattendu.');
            return ['status'=>200,'body'=>json_encode(array_shift($responses),JSON_THROW_ON_ERROR)];
        });
        return new GoogleSheetsClient($token,$http,'test-spreadsheet','Ventes Horizon');
    }

    private static function metadata(): array {return ['sheets'=>[['properties'=>['sheetId'=>7,'title'=>'Ventes Horizon','gridProperties'=>['rowCount'=>1000,'columnCount'=>26]]]]];}
}
