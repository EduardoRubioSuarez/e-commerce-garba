<?php

namespace App\Console\Commands;

use App\Services\PaymentReconciler;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'store:reconcile-payments {--limit=25 : Máximo de elementos (1–100)}';

    protected $description = 'Concilia intentos pendientes y eventos Stripe recibidos';

    public function handle(): int
    {
        $limit = $this->option('limit');
        if (filter_var($limit, FILTER_VALIDATE_INT) === false || (int) $limit < 1 || (int) $limit > 100) {
            $this->error('El límite debe estar entre 1 y 100.');

            return self::INVALID;
        }
        $completed = app(PaymentReconciler::class)->run((int) $limit);
        $this->info('Elementos recuperados: '.$completed);

        return self::SUCCESS;
    }
}
