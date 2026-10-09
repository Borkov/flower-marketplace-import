<?php

declare(strict_types=1);

namespace App\Import;

use App\Enum\ImportOutcome;
use App\Enum\OrderStatus;

final class ImportDecisionMaker
{
    public function decide(
        bool $seenInBatch,
        ?OrderStatus $existingStatus,
        ParseResult $parsed,
    ): ImportDecision {
        if ($seenInBatch) {
            return new ImportDecision(ImportOutcome::Duplicate, 'duplicate_in_batch');
        }

        if ($parsed->rejectCode !== null) {
            return new ImportDecision(
                ImportOutcome::Rejected,
                $parsed->rejectCode,
            );
        }

        $incoming = $parsed->order?->status;
        if ($incoming === null) {
            return new ImportDecision(ImportOutcome::Rejected, 'invalid_payload');
        }

        if ($existingStatus === null) {
            return new ImportDecision(ImportOutcome::Created, 'created', $incoming);
        }

        if ($existingStatus->isFinal()) {
            return new ImportDecision(ImportOutcome::Unchanged, 'final_status_locked');
        }

        if ($existingStatus === $incoming) {
            return new ImportDecision(ImportOutcome::Unchanged, 'status_unchanged');
        }

        return new ImportDecision(ImportOutcome::Updated, 'status_updated', $incoming);
    }
}
