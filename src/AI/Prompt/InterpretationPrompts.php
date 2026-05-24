<?php

namespace FlowEngine\AI\Prompt;

final class InterpretationPrompts
{
    public static function cycles(): PromptTemplate
    {
        return new PromptTemplate(
            title: 'Dependency Cycle Interpretation',
            body: <<<'BODY'
Analyze the dependency cycles detected in this codebase.
For each cycle, explain:
- Why this cycle is problematic
- Which components are most tightly coupled
- Concrete suggestions to break the cycle (e.g. introduce an interface, invert a dependency)
Prioritize cycles by severity (largest cycles and those involving core components first).
BODY
        );
    }

    public static function hotspots(): PromptTemplate
    {
        return new PromptTemplate(
            title: 'Complexity Hotspot Interpretation',
            body: <<<'BODY'
Analyze the complexity hotspots detected in this codebase.
For each hotspot, explain:
- What makes this method complex (branching, nesting, dependencies)
- The risk it poses for maintainability and bug density
- Concrete refactoring strategies to reduce complexity (e.g. extract method, replace conditional with polymorphism)
Prioritize the highest-complexity methods first.
BODY
        );
    }

    public static function impact(): PromptTemplate
    {
        return new PromptTemplate(
            title: 'Impact Analysis Interpretation',
            body: <<<'BODY'
Analyze the impact trace for the specified node.
Explain:
- The role of this node based on its upstream callers and downstream dependencies
- The blast radius of changes to this node
- Which upstream callers are most at risk if this node changes
- Which downstream dependencies are critical paths
Provide a clear summary of the change-propagation risk.
BODY
        );
    }

    public static function violations(): PromptTemplate
    {
        return new PromptTemplate(
            title: 'Architecture Violation Interpretation',
            body: <<<'BODY'
Analyze the architecture violations detected in this codebase.
For each violation, explain:
- Why this dependency direction is problematic
- The architectural principle being violated (e.g. dependency rule, layer isolation)
- Concrete steps to fix the violation (e.g. move class, introduce port/adapter, extract interface)
Prioritize violations by severity and group related violations together.
BODY
        );
    }

    public static function changeImpact(): PromptTemplate
    {
        return new PromptTemplate(
            title: 'Change Impact Report Interpretation',
            body: <<<'BODY'
Analyze the complete impact report for the specified node.
Explain:
- The overall risk level and what drives it (cycles, violations, coupling, complexity)
- Which upstream callers are most at risk if this node changes
- Which downstream dependencies are critical and why
- Whether cycles or architecture violations amplify the change risk
- Concrete recommendations: what to test, what to refactor first, and safe refactoring strategies
Prioritize actionable insights over generic advice.
BODY
        );
    }

    public static function refactorPlan(): PromptTemplate
    {
        return new PromptTemplate(
            title: 'Refactoring Plan Generation',
            body: <<<'BODY'
Generate a detailed, actionable refactoring plan for the specified node.

Your plan MUST be structured as JSON with this exact schema:
{
  "detectionReason": "2-3 sentence summary of why this node needs refactoring",
  "prerequisites": [
    {
      "type": "cycle|violation|orphan",
      "description": "What blocks refactoring",
      "affectedNodes": ["Class::method", ...],
      "severity": "LOW|MEDIUM|HIGH|CRITICAL",
      "recommendation": "How to resolve"
    }
  ],
  "steps": [
    {
      "order": 1,
      "action": "Brief imperative verb phrase",
      "target": "Class::method",
      "rationale": "Why needed",
      "priority": "LOW|MEDIUM|HIGH|CRITICAL",
      "affectedNodes": ["Class::method", ...],
      "tests": ["Test file paths or test descriptions"]
    }
  ],
  "testingGuidance": ["Specific recommendation 1", ...],
  "estimatedComplexity": 1-10
}

CRITICAL RULES:
1. ALL node references MUST use exact "Class::method" format from context
2. Prerequisites MUST come from actual cycles/violations/orphans in context
3. Steps MUST be ordered by dependency
4. Each step MUST be atomic and testable
5. Prioritize breaking cycles and resolving violations before extracting logic
6. Estimate complexity based on: steps count, affected nodes, prerequisites

Return ONLY valid JSON.
BODY
        );
    }

    public static function refactorGuidance(): PromptTemplate
    {
        return new PromptTemplate(
            title: 'Refactoring Step Guidance',
            body: <<<'BODY'
Generate detailed, actionable guidance for executing a single refactoring step.

Your response MUST be structured as JSON with this exact schema:
{
  "actionableInstructions": [
    "Concrete step-by-step instruction 1",
    "Concrete step-by-step instruction 2"
  ],
  "codePatterns": [
    "Example pattern or snippet relevant to this step"
  ],
  "warningsToAvoid": [
    "Common pitfall or anti-pattern to watch out for"
  ],
  "verificationChecklist": [
    "How to confirm this step was correctly applied"
  ],
  "estimatedEffort": "e.g. 30 minutes, 2 hours, 1 day"
}

CRITICAL RULES:
1. actionableInstructions MUST be ordered and immediately executable
2. codePatterns MUST be relevant to the step's action and target
3. warningsToAvoid MUST reflect real risks given the current metrics (cycles, violations, fan-in/out)
4. verificationChecklist MUST include measurable criteria (tests pass, metric improved, etc.)
5. estimatedEffort MUST be realistic given the blast radius and affected nodes
6. ALL output must be grounded in the context provided — no generic advice

Return ONLY valid JSON.
BODY
        );
    }

    public static function pullRequest(): PromptTemplate
    {
        return new PromptTemplate(
            title: 'Pull Request Introduction',
            body: <<<'BODY'
Write a concise 2-3 sentence introduction paragraph for this pull request explaining
why this refactoring matters from an engineering and maintainability perspective.

Focus on the concrete value delivered (reduced coupling, improved testability, lower
change risk) rather than restating the metrics. Tone: professional, clear,
developer-to-developer.

Return ONLY the paragraph text — no headings, no JSON, no bullet points, no extra formatting.
BODY
        );
    }

    /**
     * @return array<string, callable(): PromptTemplate>
     */
    public static function all(): array
    {
        return [
            'cycles' => [self::class, 'cycles'],
            'hotspots' => [self::class, 'hotspots'],
            'impact' => [self::class, 'impact'],
            'violations' => [self::class, 'violations'],
            'changeImpact' => [self::class, 'changeImpact'],
            'refactorPlan' => [self::class, 'refactorPlan'],
            'refactorGuidance' => [self::class, 'refactorGuidance'],
            'pullRequest' => [self::class, 'pullRequest'],
        ];
    }
}
