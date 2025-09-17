<?php
declare(strict_types=1);

namespace SymbolSdk\Builder;

final class AggregateCompleteBuilder extends BaseBuilder
{
    /** @var list<string> inner tx serialized bytes */
    private array $innerTx = [];

    public function addInner(string $serialized): static { $this->innerTx[] = $serialized; return $this; }

    protected function serializeForSigning(): string
    {
        return \SymbolSdk\Catbuffer\AggregateComplete::serializeForSigning(
            networkType: $this->networkType,
            deadline: $this->deadline,
            maxFee: $this->maxFee,
            innerTx: $this->innerTx
        );
    }

    protected function embedSignature(string $signature): string
    {
        return \SymbolSdk\Catbuffer\AggregateComplete::embedSignature($signature);
    }
}
