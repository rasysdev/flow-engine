<?php

namespace FlowEngine\AI;

use FlowEngine\AI\DTO\GuidedInputContext;
use FlowEngine\AI\DTO\SuggestedInputsResult;

interface GuidedAssistant
{
    /**
     * A IA apenas sugere. Nunca executa, valida ou decide.
     */
    public function suggestInputs(GuidedInputContext $context): SuggestedInputsResult;
}
