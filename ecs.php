<?php

declare(strict_types=1);

use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use PhpCsFixer\Fixer\Strict\StrictComparisonFixer;
use PhpCsFixer\Fixer\Strict\StrictParamFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;
use Symplify\EasyCodingStandard\ValueObject\Set\SetList;

return static function (ECSConfig $ecsConfig): void {

    $ecsConfig->paths([__DIR__ . '/src', __DIR__ . '/tests']);
    $ecsConfig->ruleWithConfiguration(ArraySyntaxFixer::class, [
        'syntax' => 'short',
    ]);

    // the rules previously provided by SetList::STRICT, which newer ECS
    // versions reject as a deprecated set
    $ecsConfig->rules([
        StrictComparisonFixer::class,
        StrictParamFixer::class,
        DeclareStrictTypesFixer::class,
    ]);

    // run and fix, one by one
    $ecsConfig->sets([
        SetList::SPACES,
        SetList::ARRAY,
        SetList::DOCBLOCK,
        SetList::PSR_12
    ]);
};
