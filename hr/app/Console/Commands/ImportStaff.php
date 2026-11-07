<?php

namespace App\Console\Commands;

use App\Hr\StaffImport;
use App\Models\School;
use Illuminate\Console\Command;

class ImportStaff extends Command
{
    protected $signature = 'hr:import-staff {path? : JSON prepared from the casual list} {--dry-run : Count the rows and write nothing}';

    protected $description = 'Load the 30 July 2026 casual teacher and teaching-assistant list onto the school file';

    public function handle(StaffImport $import): int
    {
        $path = $this->argument('path') ?: database_path('data/casual-staff-2026-07-30.json');
        if (! is_file($path)) {
            $this->error('The staff file is not there: '.$path);

            return self::FAILURE;
        }
        $payload = json_decode((string) file_get_contents($path), true);
        if (! is_array($payload) || ! isset($payload['people'])) {
            $this->error('The staff file is not the prepared list.');

            return self::FAILURE;
        }
        $school = School::query()->where('slug', 'living-school')->first();
        if (! $school) {
            $this->error('Living School is not in this database yet.');

            return self::FAILURE;
        }
        $result = $import->load($school, $payload, (bool) $this->option('dry-run'));
        $verb = $this->option('dry-run') ? 'Would add' : 'Added';
        $this->info($verb.' '.$result['created'].'. Updated '.$result['updated'].'. Already complete '.$result['unchanged'].'.');
        $waiting = count($result['needs_email']);
        $this->line($waiting.' '.($waiting === 1 ? 'person has' : 'people have').' no email on the sheet, so '.($waiting === 1 ? 'that person is' : 'they are').' not on the list yet.');
        foreach ($result['needs_email'] as $row) {
            $this->line(($row['legal_name'] ?? 'Unnamed').' · '.($row['list'] ?? '').' · '.($row['why'] ?? ''));
        }
        $this->line('Pay rates were left out. A WWCC expiry is stored, and the clearance stays unverified until HR records the number.');

        return self::SUCCESS;
    }
}
