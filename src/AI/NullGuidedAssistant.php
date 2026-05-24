<?php

namespace FlowEngine\AI;

use FlowEngine\AI\DTO\GuidedInputContext;
use FlowEngine\AI\DTO\SuggestedInputsResult;

final class NullGuidedAssistant implements GuidedAssistant
{
    public function suggestInputs(GuidedInputContext $context): SuggestedInputsResult
    {
        return new SuggestedInputsResult();
    }
}
