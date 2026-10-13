<?php

namespace App\Data;

final readonly class VerifiedPaymentResult
{
    /** @param list<string> $problems
     * @param  list<array{id:string,amount:int,status:string,created:int}>  $refunds
     */
    public function __construct(
        public string $attemptReference, public string $orderReference, public string $source,
        public string $evidenceId, public string $accountId, public bool $livemode,
        public string $currency, public int $amountCents, public string $state,
        public ?string $sessionId = null, public ?string $paymentIntentId = null, public ?string $chargeId = null,
        public ?int $occurredAt = null, public array $problems = [], public array $refunds = [],
        public ?string $disputeId = null, public ?string $disputeStatus = null,
    ) {}
}
