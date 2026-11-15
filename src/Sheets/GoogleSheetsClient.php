<?php

declare(strict_types=1);
namespace App\Sheets;

final class GoogleSheetsClient implements SheetsGateway
{
    private ?array $tab = null;
    private bool $headerReady = false;

    public function __construct(
        private readonly AccessTokenProvider $tokens,
        private readonly HttpTransport $http,
        private readonly string $spreadsheetId,
        private readonly string $sheetTitle,
    ) {}

    public function target(): string { return hash('sha256', $this->spreadsheetId.'|'.$this->sheetTitle); }

    public function write(int $row, array $values): void
    {
        if ($row < 2 || count($values) !== count(SaleProjection::COLUMNS)) { throw new \InvalidArgumentException('Projection Sheets invalide.'); }
        if (!preg_match('/^[A-Za-z0-9_-]{10,}$/', $this->spreadsheetId) || $this->sheetTitle === '') {
            throw new SyncFailure('Identifiant de feuille ou nom d’onglet manquant.');
        }
        $this->ensureTab($row);
        if (!$this->headerReady) {
            $header = $this->api('GET', '/values/'.$this->range('A1:P1'))['values'][0] ?? [];
            if ($header !== [] && $header !== SaleProjection::COLUMNS) {
                throw new SyncFailure('L’onglet contient un autre format ; utiliser un onglet d’export dédié.');
            }
            $this->put('A1:P1', SaleProjection::COLUMNS);
            $this->headerReady = true;
        }
        $existing = $this->api('GET', '/values/'.$this->range('A'.$row))['values'][0][0] ?? '';
        if ($existing !== '' && $existing !== $values[0]) {
            throw new SyncFailure('Les lignes de l’onglet ont été déplacées ; reprendre dans un onglet d’export neuf.');
        }
        // La ligne est réservée par l'identifiant SQL durable. Un rejeu écrase la même plage.
        $this->put('A'.$row.':P'.$row, $values);
    }

    private function ensureTab(int $row): void
    {
        if ($this->tab === null) {
            $data = $this->api('GET', '?fields=sheets.properties(sheetId,title,gridProperties)');
            foreach ($data['sheets'] ?? [] as $sheet) {
                if (($sheet['properties']['title'] ?? null) === $this->sheetTitle) { $this->tab = $sheet['properties']; break; }
            }
            if ($this->tab === null) {
                $data = $this->api('POST', ':batchUpdate', ['requests'=>[['addSheet'=>['properties'=>[
                    'title'=>$this->sheetTitle, 'gridProperties'=>['rowCount'=>max(1000,$row+100),'columnCount'=>16,'frozenRowCount'=>1],
                ]]]]]);
                $this->tab = $data['replies'][0]['addSheet']['properties'] ?? null;
                if ($this->tab === null) { throw new SyncFailure('Création de l’onglet non confirmée.'); }
            }
        }
        $grid = $this->tab['gridProperties'] ?? [];
        if (($grid['rowCount'] ?? 0) < $row || ($grid['columnCount'] ?? 0) < 16) {
            $grid['rowCount'] = max($row+100,(int)($grid['rowCount'] ?? 1000));
            $grid['columnCount'] = max(16,(int)($grid['columnCount'] ?? 16));
            $this->api('POST', ':batchUpdate', ['requests'=>[['updateSheetProperties'=>[
                'properties'=>['sheetId'=>$this->tab['sheetId'],'gridProperties'=>$grid],
                'fields'=>'gridProperties.rowCount,gridProperties.columnCount',
            ]]]]);
            $this->tab['gridProperties'] = $grid;
        }
    }

    private function range(string $cells): string { return rawurlencode("'".str_replace("'","''",$this->sheetTitle)."'!".$cells); }
    private function put(string $range, array $values): void
    {
        // RAW empêche les titres et libellés de devenir des formules.
        $this->api('PUT', '/values/'.$this->range($range).'?valueInputOption=RAW', ['majorDimension'=>'ROWS','values'=>[$values]]);
    }

    private function api(string $method, string $suffix, ?array $data = null): array
    {
        $response = $this->http->request($method,
            'https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($this->spreadsheetId).$suffix,
            ['Authorization: Bearer '.$this->tokens->token(),'Content-Type: application/json'],
            $data === null ? null : json_encode($data, JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        if ($response['status'] < 200 || $response['status'] >= 300) {
            if ($response['status'] === 401) { $this->tokens->invalidate(); }
            throw new SyncFailure('Google Sheets HTTP '.$response['status'].' ; vérifier accès, API et quota.');
        }
        try { return json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new SyncFailure('Réponse Google Sheets illisible.'); }
    }
}
