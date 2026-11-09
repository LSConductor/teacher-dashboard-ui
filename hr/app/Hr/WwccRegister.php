<?php

namespace App\Hr;

use Illuminate\Support\Carbon;

class WwccRegister
{
    public function __construct(private XlsxBook $book) {}

    /**
     * @return array{rows: array<int, array<string, mixed>>, sheets: array<int, array{sheet: string, count: int}>, ignored_sign_in: bool, skipped: array<int, string>}
     */
    public function read(string $path): array
    {
        $sheets = $this->book->sheets($path);
        $rows = [];
        $loaded = [];
        $ignored = false;
        $skipped = [];
        foreach ($sheets as $name => $grid) {
            $kind = $this->kind($name);
            if ($kind === 'skip') {
                $skipped[] = $name;

                continue;
            }
            $parsed = $this->sheet($name, $grid, $kind);
            $ignored = $ignored || $parsed['ignored_sign_in'];
            $loaded[] = ['sheet' => $name, 'count' => count($parsed['rows'])];
            foreach ($parsed['rows'] as $row) {
                $rows[] = $row;
            }
        }

        return ['rows' => $rows, 'sheets' => $loaded, 'ignored_sign_in' => $ignored, 'skipped' => $skipped];
    }

    public static function dateFromSerial(int|float|string $serial): ?string
    {
        if (! is_numeric($serial)) {
            $text = trim((string) $serial);
            if ($text === '') {
                return null;
            }
            try {
                return Carbon::parse($text, 'Australia/Sydney')->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }
        $whole = (int) floor((float) $serial);
        if ($whole < 1 || $whole > 80000) {
            return null;
        }

        return Carbon::create(1899, 12, 30, 0, 0, 0, 'UTC')->addDays($whole)->toDateString();
    }

    public static function serialFromDate(string $date): int
    {
        $start = Carbon::create(1899, 12, 30, 0, 0, 0, 'UTC');
        $end = Carbon::parse($date, 'UTC')->startOfDay();

        return (int) $start->diffInDays($end, false);
    }

    private function kind(string $name): string
    {
        $key = (string) preg_replace('/[^a-z0-9]/', '', strtolower($name));
        if ($key === '' || str_contains($key, 'previous') || str_contains($key, 'overview')) {
            return 'skip';
        }

        return match (true) {
            $key === 'pending' => 'pending',
            $key === 'currentstaff' => 'staff',
            str_starts_with($key, 'currentexternalcontractor') => 'staff',
            $key === 'parentsvolunteers' => 'helper',
            str_starts_with($key, 'guardians') => 'helper',
            default => 'skip',
        };
    }

    /**
     * @param  array<int, array<int, string>>  $grid
     * @return array{rows: array<int, array<string, mixed>>, ignored_sign_in: bool}
     */
    private function sheet(string $name, array $grid, string $kind): array
    {
        $secrets = [];
        $ignored = false;
        $header = null;
        $headerAt = null;
        foreach ($grid as $index => $line) {
            $found = $this->headerMap($line);
            if ($found !== null) {
                $header = $found;
                $headerAt = $index;
                break;
            }
            if ($this->credentialRow($line)) {
                $ignored = true;
                foreach ($line as $cell) {
                    foreach ($this->secretsFrom($cell) as $secret) {
                        $secrets[] = $secret;
                    }
                }
            }
        }
        if ($header === null || $headerAt === null) {
            return ['rows' => [], 'ignored_sign_in' => $ignored];
        }
        $rows = [];
        foreach (array_slice($grid, $headerAt + 1) as $line) {
            $row = $this->record($name, $kind, $line, $header, $secrets);
            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return ['rows' => $rows, 'ignored_sign_in' => $ignored];
    }

    /**
     * @param  array<int, string>  $line
     */
    private function credentialRow(array $line): bool
    {
        $filled = 0;
        $hit = false;
        foreach ($line as $cell) {
            $text = strtolower(trim($cell));
            if ($text === '') {
                continue;
            }
            $filled++;
            if (preg_match('/password|user\s*name|user\s*id|^login$|children.?s guardian|office of the children/', $text) === 1) {
                $hit = true;
            }
        }

        return $hit && $filled <= 3;
    }

    /**
     * @return array<int, string>
     */
    private function secretsFrom(string $cell): array
    {
        $secret = trim($cell);
        if (preg_match('/(?:user\s*name|user\s*id|password|login)\s*[:\-]\s*(.+)$/i', $secret, $match) === 1) {
            $tail = trim($match[1]);

            return strlen($tail) >= 4 ? [$tail] : [];
        }
        if (! $this->keepSecret($secret)) {
            return [];
        }

        return [$secret];
    }

    private function keepSecret(string $secret): bool
    {
        if (strlen($secret) < 4) {
            return false;
        }
        $text = strtolower($secret);
        if (preg_match('/password|user\s*name|user\s*id|^login$|children.?s guardian|office of the children/', $text) === 1) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<int, string>  $line
     * @return array<string, int>|null
     */
    private function headerMap(array $line): ?array
    {
        $aliases = [
            'name' => ['fullname', 'name', 'staffname', 'legalname', 'employeename'],
            'surname' => ['surname', 'lastname', 'familyname'],
            'given' => ['givenname', 'givennames', 'firstname', 'forename'],
            'role' => ['positionorrole', 'positionrole', 'position', 'role'],
            'dob' => ['dateofbirth', 'dob', 'birthdate'],
            'number' => ['wwccnumber', 'wwccno', 'wwcc', 'clearancenumber', 'workingwithchildrennumber'],
            'start' => ['startdate', 'start'],
            'verified' => ['verificationdate', 'dateverified', 'verified'],
            'result' => ['result', 'outcome'],
            'expiry' => ['expirydate', 'expiry', 'expires', 'expiryofwwcc'],
            'paid' => ['paidorvolunteer', 'paidvolunteer', 'payment'],
            'new_expiry' => ['newexpiry', 'newexpirydate', 'renewedexpiry'],
            'latest' => ['latestcheck', 'lastcheck', 'latestverification'],
            'notes' => ['notes', 'note', 'comments'],
        ];
        $map = [];
        foreach ($line as $index => $cell) {
            $key = (string) preg_replace('/[^a-z0-9]/', '', strtolower($cell));
            foreach ($aliases as $field => $names) {
                if (in_array($key, $names, true) && ! isset($map[$field])) {
                    $map[$field] = $index;
                }
            }
        }
        $named = isset($map['name']) || (isset($map['given']) && isset($map['surname']));
        if (! $named || (! isset($map['number']) && ! isset($map['expiry']) && ! isset($map['dob']))) {
            return null;
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $line
     * @param  array<string, int>  $header
     * @param  array<int, string>  $secrets
     * @return array<string, mixed>|null
     */
    private function record(string $sheet, string $kind, array $line, array $header, array $secrets): ?array
    {
        $name = $this->personName($line, $header, $secrets);
        if ($name === null || $this->headerMap([$name]) !== null) {
            return null;
        }
        $number = $this->clean($this->cell($line, $header, 'number'), $secrets);
        if ($number !== null) {
            $number = strtoupper(preg_replace('/\s+/', '', $number) ?? '');
        }
        $expiry = $this->cleanDate($this->cell($line, $header, 'expiry'), $secrets);
        $renewed = $this->cleanDate($this->cell($line, $header, 'new_expiry'), $secrets);
        if ($renewed !== null && ($expiry === null || $renewed > $expiry)) {
            $expiry = $renewed;
        }

        return [
            'sheet' => $sheet,
            'kind' => $kind,
            'name' => $name,
            'role' => $this->clean($this->cell($line, $header, 'role'), $secrets),
            'dob' => $this->cleanDate($this->cell($line, $header, 'dob'), $secrets),
            'number' => $number,
            'verified_on' => $this->cleanDate($this->cell($line, $header, 'latest'), $secrets)
                ?? $this->cleanDate($this->cell($line, $header, 'verified'), $secrets),
            'result' => $this->clean($this->cell($line, $header, 'result'), $secrets),
            'expiry' => $expiry,
        ];
    }

    /**
     * @param  array<int, string>  $line
     * @param  array<string, int>  $header
     * @param  array<int, string>  $secrets
     */
    private function personName(array $line, array $header, array $secrets): ?string
    {
        $full = $this->clean($this->cell($line, $header, 'name'), $secrets);
        if ($full !== null) {
            return $full;
        }
        $given = $this->clean($this->cell($line, $header, 'given'), $secrets);
        $surname = $this->clean($this->cell($line, $header, 'surname'), $secrets);
        $joined = trim((string) $given.' '.(string) $surname);

        return $joined === '' ? null : $joined;
    }

    /**
     * @param  array<int, string>  $line
     * @param  array<string, int>  $header
     */
    private function cell(array $line, array $header, string $field): string
    {
        $index = $header[$field] ?? null;

        return $index === null ? '' : (string) ($line[$index] ?? '');
    }

    /**
     * @param  array<int, string>  $secrets
     */
    private function clean(string $value, array $secrets): ?string
    {
        $value = trim($value);
        if ($value === '' || in_array($value, $secrets, true)) {
            return null;
        }

        return $value;
    }

    /**
     * @param  array<int, string>  $secrets
     */
    private function cleanDate(string $value, array $secrets): ?string
    {
        $value = $this->clean($value, $secrets);
        if ($value === null) {
            return null;
        }

        return self::dateFromSerial($value);
    }
}
