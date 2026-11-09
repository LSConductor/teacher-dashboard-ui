<?php

namespace App\Console\Commands;

use App\Hr\WwccImport;
use App\Hr\WwccRegister;
use App\Models\School;
use Illuminate\Console\Command;
use RuntimeException;

class ImportWwcc extends Command
{
    protected $signature = 'hr:import-wwcc {path : The 2026 WWCC register workbook} {--dry-run : Count the rows and write nothing}';

    protected $description = 'Load the 2026 WWCC register onto clearances already on the school file';

    public function handle(WwccRegister $register, WwccImport $import): int
    {
        $path = (string) $this->argument('path');
        if (! is_file($path)) {
            $this->error('The WWCC register is not there: '.$path);

            return self::FAILURE;
        }
        try {
            $book = $register->read($path);
        } catch (RuntimeException) {
            $this->error('That file is not an Excel workbook.');

            return self::FAILURE;
        }
        $school = School::query()->where('slug', 'living-school')->first();
        if (! $school) {
            $this->error('Living School is not in this database yet.');

            return self::FAILURE;
        }
        $result = $import->load($school, $book, (bool) $this->option('dry-run'));
        foreach ($result['sheets'] ?? [] as $sheet) {
            $note = ($sheet['header'] ?? true) ? '' : ', no header row';
            $this->line($sheet['sheet'].': '.$sheet['count'].' '.($sheet['count'] === 1 ? 'name' : 'names').$note.'.');
        }
        $verb = $this->option('dry-run') ? 'Would update' : 'Updated';
        $this->info($verb.' '.$result['updated'].' clearances. Already complete '.$result['unchanged'].'.');
        $waiting = count($result['reported']);
        $this->line($waiting.' '.($waiting === 1 ? 'name is' : 'names are').' not on the casual list.');
        foreach ($result['reported'] as $row) {
            $this->line($row['name'].' · '.$row['sheet'].' · '.$row['why']);
        }
        if ($result['skipped'] !== []) {
            $this->line('Skipped '.implode(', ', $result['skipped']).'.');
        }
        if ($result['ignored_sign_in']) {
            $this->line('The Guardian sign-in on Current Staff was ignored.');
        }
        $this->line('An in-date clearance is marked checked. A date that has passed stays red. Parents and guardians are helpers, not casual teachers.');

        return self::SUCCESS;
    }
}
