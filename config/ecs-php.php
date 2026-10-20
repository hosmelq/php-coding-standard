<?php

declare(strict_types=1);

use HosmelQ\PhpCodingStandard\Fixer\HeredocBlankLineFixer;
use HosmelQ\PhpCodingStandard\Fixer\LineLengthFixer;
use HosmelQ\PhpCodingStandard\Fixer\NoBlankLineBetweenAssignmentsFixer;
use PhpCsFixer\Fixer\Alias\ArrayPushFixer;
use PhpCsFixer\Fixer\Alias\MbStrFunctionsFixer;
use PhpCsFixer\Fixer\Alias\ModernizeStrposFixer;
use PhpCsFixer\Fixer\Alias\NoAliasFunctionsFixer;
use PhpCsFixer\Fixer\Alias\SetTypeToCastFixer;
use PhpCsFixer\Fixer\ArrayNotation\ArraySyntaxFixer;
use PhpCsFixer\Fixer\ArrayNotation\NoWhitespaceBeforeCommaInArrayFixer;
use PhpCsFixer\Fixer\ArrayNotation\TrimArraySpacesFixer;
use PhpCsFixer\Fixer\ArrayNotation\WhitespaceAfterCommaInArrayFixer;
use PhpCsFixer\Fixer\AttributeNotation\AttributeEmptyParenthesesFixer;
use PhpCsFixer\Fixer\Basic\BracesPositionFixer;
use PhpCsFixer\Fixer\Basic\EncodingFixer;
use PhpCsFixer\Fixer\Basic\NoMultipleStatementsPerLineFixer;
use PhpCsFixer\Fixer\Basic\NoTrailingCommaInSinglelineFixer;
use PhpCsFixer\Fixer\Basic\NumericLiteralSeparatorFixer;
use PhpCsFixer\Fixer\Basic\OctalNotationFixer;
use PhpCsFixer\Fixer\Basic\SingleLineEmptyBodyFixer;
use PhpCsFixer\Fixer\Casing\ClassReferenceNameCasingFixer;
use PhpCsFixer\Fixer\Casing\ConstantCaseFixer;
use PhpCsFixer\Fixer\Casing\IntegerLiteralCaseFixer;
use PhpCsFixer\Fixer\Casing\LowercaseKeywordsFixer;
use PhpCsFixer\Fixer\Casing\LowercaseStaticReferenceFixer;
use PhpCsFixer\Fixer\Casing\MagicConstantCasingFixer;
use PhpCsFixer\Fixer\Casing\MagicMethodCasingFixer;
use PhpCsFixer\Fixer\Casing\NativeFunctionCasingFixer;
use PhpCsFixer\Fixer\Casing\NativeTypeDeclarationCasingFixer;
use PhpCsFixer\Fixer\CastNotation\CastSpacesFixer;
use PhpCsFixer\Fixer\CastNotation\LowercaseCastFixer;
use PhpCsFixer\Fixer\CastNotation\ModernizeTypesCastingFixer;
use PhpCsFixer\Fixer\CastNotation\ShortScalarCastFixer;
use PhpCsFixer\Fixer\ClassNotation\ClassAttributesSeparationFixer;
use PhpCsFixer\Fixer\ClassNotation\ClassDefinitionFixer;
use PhpCsFixer\Fixer\ClassNotation\ModifierKeywordsFixer;
use PhpCsFixer\Fixer\ClassNotation\NoBlankLinesAfterClassOpeningFixer;
use PhpCsFixer\Fixer\ClassNotation\NoNullPropertyInitializationFixer;
use PhpCsFixer\Fixer\ClassNotation\NoUnneededFinalMethodFixer;
use PhpCsFixer\Fixer\ClassNotation\OrderedClassElementsFixer;
use PhpCsFixer\Fixer\ClassNotation\OrderedInterfacesFixer;
use PhpCsFixer\Fixer\ClassNotation\OrderedTraitsFixer;
use PhpCsFixer\Fixer\ClassNotation\OrderedTypesFixer;
use PhpCsFixer\Fixer\ClassNotation\ProtectedToPrivateFixer;
use PhpCsFixer\Fixer\ClassNotation\SelfAccessorFixer;
use PhpCsFixer\Fixer\ClassNotation\SelfStaticAccessorFixer;
use PhpCsFixer\Fixer\ClassNotation\SingleClassElementPerStatementFixer;
use PhpCsFixer\Fixer\ClassNotation\SingleTraitInsertPerStatementFixer;
use PhpCsFixer\Fixer\ClassUsage\DateTimeImmutableFixer;
use PhpCsFixer\Fixer\Comment\NoEmptyCommentFixer;
use PhpCsFixer\Fixer\Comment\NoTrailingWhitespaceInCommentFixer;
use PhpCsFixer\Fixer\Comment\SingleLineCommentSpacingFixer;
use PhpCsFixer\Fixer\ControlStructure\ControlStructureBracesFixer;
use PhpCsFixer\Fixer\ControlStructure\ControlStructureContinuationPositionFixer;
use PhpCsFixer\Fixer\ControlStructure\ElseifFixer;
use PhpCsFixer\Fixer\ControlStructure\NoBreakCommentFixer;
use PhpCsFixer\Fixer\ControlStructure\NoSuperfluousElseifFixer;
use PhpCsFixer\Fixer\ControlStructure\NoUnneededBracesFixer;
use PhpCsFixer\Fixer\ControlStructure\NoUnneededControlParenthesesFixer;
use PhpCsFixer\Fixer\ControlStructure\NoUselessElseFixer;
use PhpCsFixer\Fixer\ControlStructure\SimplifiedIfReturnFixer;
use PhpCsFixer\Fixer\ControlStructure\SwitchCaseSemicolonToColonFixer;
use PhpCsFixer\Fixer\ControlStructure\SwitchCaseSpaceFixer;
use PhpCsFixer\Fixer\ControlStructure\TrailingCommaInMultilineFixer;
use PhpCsFixer\Fixer\FunctionNotation\FunctionDeclarationFixer;
use PhpCsFixer\Fixer\FunctionNotation\LambdaNotUsedImportFixer;
use PhpCsFixer\Fixer\FunctionNotation\MethodArgumentSpaceFixer;
use PhpCsFixer\Fixer\FunctionNotation\NoSpacesAfterFunctionNameFixer;
use PhpCsFixer\Fixer\FunctionNotation\NoUselessSprintfFixer;
use PhpCsFixer\Fixer\FunctionNotation\ReturnTypeDeclarationFixer;
use PhpCsFixer\Fixer\FunctionNotation\VoidReturnFixer;
use PhpCsFixer\Fixer\Import\FullyQualifiedStrictTypesFixer;
use PhpCsFixer\Fixer\Import\GlobalNamespaceImportFixer;
use PhpCsFixer\Fixer\Import\NoLeadingImportSlashFixer;
use PhpCsFixer\Fixer\Import\NoUnneededImportAliasFixer;
use PhpCsFixer\Fixer\Import\NoUnusedImportsFixer;
use PhpCsFixer\Fixer\Import\OrderedImportsFixer;
use PhpCsFixer\Fixer\Import\SingleLineAfterImportsFixer;
use PhpCsFixer\Fixer\LanguageConstruct\CombineConsecutiveIssetsFixer;
use PhpCsFixer\Fixer\LanguageConstruct\CombineConsecutiveUnsetsFixer;
use PhpCsFixer\Fixer\LanguageConstruct\DeclareEqualNormalizeFixer;
use PhpCsFixer\Fixer\LanguageConstruct\DirConstantFixer;
use PhpCsFixer\Fixer\LanguageConstruct\FunctionToConstantFixer;
use PhpCsFixer\Fixer\LanguageConstruct\GetClassToClassKeywordFixer;
use PhpCsFixer\Fixer\LanguageConstruct\NullableTypeDeclarationFixer;
use PhpCsFixer\Fixer\LanguageConstruct\SingleSpaceAroundConstructFixer;
use PhpCsFixer\Fixer\ListNotation\ListSyntaxFixer;
use PhpCsFixer\Fixer\NamespaceNotation\BlankLineAfterNamespaceFixer;
use PhpCsFixer\Fixer\NamespaceNotation\BlankLinesBeforeNamespaceFixer;
use PhpCsFixer\Fixer\Operator\AssignNullCoalescingToCoalesceEqualFixer;
use PhpCsFixer\Fixer\Operator\BinaryOperatorSpacesFixer;
use PhpCsFixer\Fixer\Operator\ConcatSpaceFixer;
use PhpCsFixer\Fixer\Operator\LogicalOperatorsFixer;
use PhpCsFixer\Fixer\Operator\LongToShorthandOperatorFixer;
use PhpCsFixer\Fixer\Operator\NewWithParenthesesFixer;
use PhpCsFixer\Fixer\Operator\NoSpaceAroundDoubleColonFixer;
use PhpCsFixer\Fixer\Operator\NotOperatorWithSuccessorSpaceFixer;
use PhpCsFixer\Fixer\Operator\NoUselessConcatOperatorFixer;
use PhpCsFixer\Fixer\Operator\NoUselessNullsafeOperatorFixer;
use PhpCsFixer\Fixer\Operator\ObjectOperatorWithoutWhitespaceFixer;
use PhpCsFixer\Fixer\Operator\StandardizeNotEqualsFixer;
use PhpCsFixer\Fixer\Operator\TernaryOperatorSpacesFixer;
use PhpCsFixer\Fixer\Operator\TernaryToElvisOperatorFixer;
use PhpCsFixer\Fixer\Operator\TernaryToNullCoalescingFixer;
use PhpCsFixer\Fixer\Operator\UnaryOperatorSpacesFixer;
use PhpCsFixer\Fixer\Phpdoc\GeneralPhpdocTagRenameFixer;
use PhpCsFixer\Fixer\Phpdoc\NoBlankLinesAfterPhpdocFixer;
use PhpCsFixer\Fixer\Phpdoc\NoEmptyPhpdocFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocAddMissingParamAnnotationFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocAlignFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocArrayTypeFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocIndentFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocInlineTagNormalizerFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocLineSpanFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocNoDuplicateTypesFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocNoEmptyReturnFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocNoPackageFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocNoUselessInheritdocFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocOrderFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocParamOrderFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocScalarFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocSeparationFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocSingleLineVarSpacingFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocSummaryFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocTagCasingFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocTrimConsecutiveBlankLineSeparationFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocTrimFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocTypesFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocTypesOrderFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocVarAnnotationCorrectOrderFixer;
use PhpCsFixer\Fixer\Phpdoc\PhpdocVarWithoutNameFixer;
use PhpCsFixer\Fixer\PhpTag\BlankLineAfterOpeningTagFixer;
use PhpCsFixer\Fixer\PhpTag\FullOpeningTagFixer;
use PhpCsFixer\Fixer\PhpTag\NoClosingTagFixer;
use PhpCsFixer\Fixer\ReturnNotation\NoUselessReturnFixer;
use PhpCsFixer\Fixer\ReturnNotation\ReturnAssignmentFixer;
use PhpCsFixer\Fixer\Semicolon\MultilineWhitespaceBeforeSemicolonsFixer;
use PhpCsFixer\Fixer\Semicolon\NoEmptyStatementFixer;
use PhpCsFixer\Fixer\Strict\DeclareStrictTypesFixer;
use PhpCsFixer\Fixer\Strict\StrictComparisonFixer;
use PhpCsFixer\Fixer\Strict\StrictParamFixer;
use PhpCsFixer\Fixer\StringNotation\ExplicitStringVariableFixer;
use PhpCsFixer\Fixer\StringNotation\HeredocToNowdocFixer;
use PhpCsFixer\Fixer\StringNotation\SingleQuoteFixer;
use PhpCsFixer\Fixer\StringNotation\StringLengthToEmptyFixer;
use PhpCsFixer\Fixer\Whitespace\ArrayIndentationFixer;
use PhpCsFixer\Fixer\Whitespace\BlankLineBeforeStatementFixer;
use PhpCsFixer\Fixer\Whitespace\BlankLineBetweenImportGroupsFixer;
use PhpCsFixer\Fixer\Whitespace\CompactNullableTypeDeclarationFixer;
use PhpCsFixer\Fixer\Whitespace\HeredocIndentationFixer;
use PhpCsFixer\Fixer\Whitespace\IndentationTypeFixer;
use PhpCsFixer\Fixer\Whitespace\LineEndingFixer;
use PhpCsFixer\Fixer\Whitespace\MethodChainingIndentationFixer;
use PhpCsFixer\Fixer\Whitespace\NoExtraBlankLinesFixer;
use PhpCsFixer\Fixer\Whitespace\NoSpacesAroundOffsetFixer;
use PhpCsFixer\Fixer\Whitespace\NoTrailingWhitespaceFixer;
use PhpCsFixer\Fixer\Whitespace\NoWhitespaceInBlankLineFixer;
use PhpCsFixer\Fixer\Whitespace\SingleBlankLineAtEofFixer;
use PhpCsFixer\Fixer\Whitespace\SpacesInsideParenthesesFixer;
use PhpCsFixer\Fixer\Whitespace\StatementIndentationFixer;
use PhpCsFixer\Fixer\Whitespace\TypeDeclarationSpacesFixer;
use PhpCsFixer\Fixer\Whitespace\TypesSpacesFixer;
use Symplify\EasyCodingStandard\Config\ECSConfig;

return ECSConfig::configure()
    ->withRules([
        HeredocBlankLineFixer::class,
        LineLengthFixer::class,
        NoBlankLineBetweenAssignmentsFixer::class,
        ArrayPushFixer::class,
        MbStrFunctionsFixer::class,
        ModernizeStrposFixer::class,
        NoAliasFunctionsFixer::class,
        SetTypeToCastFixer::class,
        ArraySyntaxFixer::class,
        NoWhitespaceBeforeCommaInArrayFixer::class,
        TrimArraySpacesFixer::class,
        WhitespaceAfterCommaInArrayFixer::class,
        AttributeEmptyParenthesesFixer::class,
        EncodingFixer::class,
        NoMultipleStatementsPerLineFixer::class,
        NoTrailingCommaInSinglelineFixer::class,
        NumericLiteralSeparatorFixer::class,
        OctalNotationFixer::class,
        SingleLineEmptyBodyFixer::class,
        ClassReferenceNameCasingFixer::class,
        ConstantCaseFixer::class,
        IntegerLiteralCaseFixer::class,
        LowercaseKeywordsFixer::class,
        LowercaseStaticReferenceFixer::class,
        MagicConstantCasingFixer::class,
        MagicMethodCasingFixer::class,
        NativeFunctionCasingFixer::class,
        NativeTypeDeclarationCasingFixer::class,
        CastSpacesFixer::class,
        LowercaseCastFixer::class,
        ModernizeTypesCastingFixer::class,
        ShortScalarCastFixer::class,
        ClassAttributesSeparationFixer::class,
        ModifierKeywordsFixer::class,
        NoBlankLinesAfterClassOpeningFixer::class,
        NoNullPropertyInitializationFixer::class,
        NoUnneededFinalMethodFixer::class,
        OrderedInterfacesFixer::class,
        OrderedTraitsFixer::class,
        OrderedTypesFixer::class,
        ProtectedToPrivateFixer::class,
        SelfAccessorFixer::class,
        SelfStaticAccessorFixer::class,
        SingleTraitInsertPerStatementFixer::class,
        DateTimeImmutableFixer::class,
        NoEmptyCommentFixer::class,
        NoTrailingWhitespaceInCommentFixer::class,
        SingleLineCommentSpacingFixer::class,
        ControlStructureBracesFixer::class,
        ControlStructureContinuationPositionFixer::class,
        ElseifFixer::class,
        NoBreakCommentFixer::class,
        NoSuperfluousElseifFixer::class,
        NoUnneededBracesFixer::class,
        NoUnneededControlParenthesesFixer::class,
        NoUselessElseFixer::class,
        SimplifiedIfReturnFixer::class,
        SwitchCaseSemicolonToColonFixer::class,
        SwitchCaseSpaceFixer::class,
        LambdaNotUsedImportFixer::class,
        NoSpacesAfterFunctionNameFixer::class,
        NoUselessSprintfFixer::class,
        ReturnTypeDeclarationFixer::class,
        VoidReturnFixer::class,
        GlobalNamespaceImportFixer::class,
        NoLeadingImportSlashFixer::class,
        NoUnneededImportAliasFixer::class,
        NoUnusedImportsFixer::class,
        SingleLineAfterImportsFixer::class,
        CombineConsecutiveIssetsFixer::class,
        CombineConsecutiveUnsetsFixer::class,
        DeclareEqualNormalizeFixer::class,
        DirConstantFixer::class,
        FunctionToConstantFixer::class,
        GetClassToClassKeywordFixer::class,
        ListSyntaxFixer::class,
        BlankLineAfterNamespaceFixer::class,
        BlankLinesBeforeNamespaceFixer::class,
        AssignNullCoalescingToCoalesceEqualFixer::class,
        ConcatSpaceFixer::class,
        LogicalOperatorsFixer::class,
        LongToShorthandOperatorFixer::class,
        NoSpaceAroundDoubleColonFixer::class,
        NoUselessConcatOperatorFixer::class,
        NoUselessNullsafeOperatorFixer::class,
        NotOperatorWithSuccessorSpaceFixer::class,
        ObjectOperatorWithoutWhitespaceFixer::class,
        StandardizeNotEqualsFixer::class,
        TernaryOperatorSpacesFixer::class,
        TernaryToElvisOperatorFixer::class,
        TernaryToNullCoalescingFixer::class,
        UnaryOperatorSpacesFixer::class,
        NoBlankLinesAfterPhpdocFixer::class,
        NoEmptyPhpdocFixer::class,
        PhpdocAddMissingParamAnnotationFixer::class,
        PhpdocArrayTypeFixer::class,
        PhpdocIndentFixer::class,
        PhpdocInlineTagNormalizerFixer::class,
        PhpdocLineSpanFixer::class,
        PhpdocNoDuplicateTypesFixer::class,
        PhpdocNoEmptyReturnFixer::class,
        PhpdocNoPackageFixer::class,
        PhpdocNoUselessInheritdocFixer::class,
        PhpdocParamOrderFixer::class,
        PhpdocScalarFixer::class,
        PhpdocSeparationFixer::class,
        PhpdocSingleLineVarSpacingFixer::class,
        PhpdocSummaryFixer::class,
        PhpdocTagCasingFixer::class,
        PhpdocTrimConsecutiveBlankLineSeparationFixer::class,
        PhpdocTrimFixer::class,
        PhpdocTypesFixer::class,
        PhpdocTypesOrderFixer::class,
        PhpdocVarAnnotationCorrectOrderFixer::class,
        PhpdocVarWithoutNameFixer::class,
        BlankLineAfterOpeningTagFixer::class,
        FullOpeningTagFixer::class,
        NoClosingTagFixer::class,
        NoUselessReturnFixer::class,
        ReturnAssignmentFixer::class,
        MultilineWhitespaceBeforeSemicolonsFixer::class,
        NoEmptyStatementFixer::class,
        DeclareStrictTypesFixer::class,
        StrictComparisonFixer::class,
        StrictParamFixer::class,
        ExplicitStringVariableFixer::class,
        HeredocToNowdocFixer::class,
        SingleQuoteFixer::class,
        StringLengthToEmptyFixer::class,
        ArrayIndentationFixer::class,
        BlankLineBetweenImportGroupsFixer::class,
        CompactNullableTypeDeclarationFixer::class,
        HeredocIndentationFixer::class,
        IndentationTypeFixer::class,
        LineEndingFixer::class,
        MethodChainingIndentationFixer::class,
        NoSpacesAroundOffsetFixer::class,
        NoTrailingWhitespaceFixer::class,
        NoWhitespaceInBlankLineFixer::class,
        SingleBlankLineAtEofFixer::class,
        SpacesInsideParenthesesFixer::class,
        StatementIndentationFixer::class,
        TypeDeclarationSpacesFixer::class,
        TypesSpacesFixer::class,
    ])
    ->withConfiguredRule(BracesPositionFixer::class, [
        'allow_single_line_anonymous_functions' => false,
        'allow_single_line_empty_anonymous_classes' => true,
    ])
    ->withConfiguredRule(ClassDefinitionFixer::class, [
        'inline_constructor_arguments' => false,
        'space_before_parenthesis' => true,
    ])
    ->withConfiguredRule(OrderedClassElementsFixer::class, [
        'order' => [
            'use_trait',
            'case',
            'constant',
            'constant_public',
            'constant_protected',
            'constant_private',
            'property_public',
            'property_protected',
            'property_private',
            'construct',
            'destruct',
            'method_abstract',
            'method_public_static',
            'method_public',
            'method_protected_static',
            'method_protected',
            'method_private_static',
            'method_private',
            'magic',
        ],
        'sort_algorithm' => 'alpha',
    ])
    ->withConfiguredRule(SingleClassElementPerStatementFixer::class, [
        'elements' => [
            'property',
        ],
    ])
    ->withConfiguredRule(TrailingCommaInMultilineFixer::class, [
        'elements' => [
            'arguments',
            'arrays',
            'parameters',
        ],
    ])
    ->withConfiguredRule(FunctionDeclarationFixer::class, [
        'closure_fn_spacing' => 'one',
    ])
    ->withConfiguredRule(MethodArgumentSpaceFixer::class, [
        'after_heredoc' => false,
        'attribute_placement' => 'ignore',
        'on_multiline' => 'ensure_fully_multiline',
    ])
    ->withConfiguredRule(FullyQualifiedStrictTypesFixer::class, [
        'import_symbols' => true,
    ])
    ->withConfiguredRule(OrderedImportsFixer::class, [
        'imports_order' => [
            'const',
            'function',
            'class',
        ],
        'sort_algorithm' => 'alpha',
    ])
    ->withConfiguredRule(NullableTypeDeclarationFixer::class, [
        'syntax' => 'union',
    ])
    ->withConfiguredRule(SingleSpaceAroundConstructFixer::class, [
        'constructs_followed_by_a_single_space' => [
            'abstract',
            'as',
            'case',
            'catch',
            'class',
            'const_import',
            'do',
            'else',
            'elseif',
            'final',
            'finally',
            'for',
            'foreach',
            'function',
            'function_import',
            'if',
            'insteadof',
            'interface',
            'namespace',
            'new',
            'private',
            'protected',
            'public',
            'static',
            'switch',
            'trait',
            'try',
            'use',
            'use_lambda',
            'while',
        ],
        'constructs_preceded_by_a_single_space' => [
            'as',
            'else',
            'elseif',
            'use_lambda',
        ],
    ])
    ->withConfiguredRule(BinaryOperatorSpacesFixer::class, [
        'default' => 'single_space',
    ])
    ->withConfiguredRule(NewWithParenthesesFixer::class, [
        'anonymous_class' => true,
    ])
    ->withConfiguredRule(GeneralPhpdocTagRenameFixer::class, [
        'replacements' => [
            'inheritdocs' => 'inheritDoc',
        ],
    ])
    ->withConfiguredRule(PhpdocAlignFixer::class, [
        'align' => 'left',
        'spacing' => 1,
    ])
    ->withConfiguredRule(PhpdocOrderFixer::class, [
        'order' => [
            'param',
            'return',
            'throws',
        ],
    ])
    ->withConfiguredRule(BlankLineBeforeStatementFixer::class, [
        'statements' => [
            'break',
            'case',
            'continue',
            'do',
            'for',
            'foreach',
            'if',
            'return',
            'switch',
            'throw',
            'try',
            'while',
        ],
    ])
    ->withConfiguredRule(NoExtraBlankLinesFixer::class, [
        'tokens' => [
            'extra',
            'throw',
            'use',
        ],
    ]);
