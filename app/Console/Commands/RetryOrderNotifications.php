<?php

namespace App\Console\Commands;

use App\Services\PaymentReconciler;
use Illuminate\Console\Command;

class RetryOrderNotifications extends Command
{
    protected $signature = 'store:retry-notifications {--limit=25 : Máximo de avisos (1–100)}';

    protected $description = 'Recupera avisos pendientes y claims vencidos con elegibilidad local';

    public function handle(): int
    {
        $limit = $this->option('limit');
        if (filter_var($limit, FILTER_VALIDATE_INT) === false || (int) $limit < 1 || (int) $limit > 100) {
            $this->error('El límite debe estar entre 1 y 100.');

            return self::INVALID;
        }
        $completed = app(PaymentReconciler::class)->retryNotifications((int) $limit);
        $this->info('Avisos aceptados: '.$completed);

        return self::SUCCESS;
    }
}
