<?php

declare(strict_types=1);
namespace App\Tests\Support;
use App\Sheets\SheetsGateway;
final class RecordingSheetsGateway implements SheetsGateway
{
    public array $rows = [];
    public array $writes = [];
    public bool $failAfterWrite = false;
    public ?\Closure $duringWrite = null;
    public string $destination = 'test-spreadsheet';
    public function target(): string { return hash('sha256',$this->destination); }
    public function write(int $row,array $values): void
    {
        $this->rows[$row]=$values; $this->writes[]=$row;
        if ($this->duringWrite !== null) { $hook=$this->duringWrite; $this->duringWrite=null; $hook(); }
        if ($this->failAfterWrite) { throw new \RuntimeException('Délai dépassé après une écriture simulée.'); }
    }
}
