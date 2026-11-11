<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProvisionMaster extends Command
{
    protected $signature = 'ccdae:provision-master';
    protected $description = 'Cria a conta master única com senha inicial aleatória';

    public function handle(): int
    {
        $email = trim((string) config('ccdae.master_email'));
        if ($email === '') { $this->error('Defina CCDAE_MASTER_EMAIL antes de executar.'); return self::FAILURE; }
        if (User::query()->where('email', $email)->exists()) { $this->info('A conta master já existe; nenhuma credencial foi alterada.'); return self::SUCCESS; }
        $password = Str::random(40);
        $path = storage_path('app/private/master-credential.txt');
        if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true) && !is_dir(dirname($path))) {
            $this->error('Não foi possível preparar o arquivo de credencial.'); return self::FAILURE;
        }
        if (file_exists($path)) { $this->error('Já existe uma credencial inicial. Inspecione-a antes de provisionar.'); return self::FAILURE; }
        $handle = fopen($path, 'x');
        if (!$handle) { $this->error('Não foi possível criar o arquivo de credencial.'); return self::FAILURE; }
        chmod($path, 0600);
        try {
            fwrite($handle, "E-mail: $email\nSenha inicial: $password\nAltere a senha após o primeiro login.\n");
            fclose($handle);
            DB::transaction(fn () => User::query()->create(['name' => 'Master CCDAE', 'email' => $email, 'password' => $password]));
        } catch (\Throwable $error) {
            if (is_resource($handle)) fclose($handle);
            @unlink($path);
            throw $error;
        }
        $this->info('Conta master criada. Credencial inicial em '.$path);
        return self::SUCCESS;
    }
}
