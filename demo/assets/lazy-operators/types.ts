export type ArgumentKind = 'expression' | 'list' | 'enum' | 'cases' | 'native' | 'callback';

export type CatalogArgument = {
    name: string;
    kind: ArgumentKind;
    type: string;
    accepts: string[];
    nativeType: string | null;
    optional: boolean;
    defaultValue: string | null;
    description: string | null;
    options: string[] | null;
    fields: CatalogArgument[] | null;
};

export type CatalogEntry = {
    key: string;
    version: string;
    name: string;
    group: string;
    formula: string | null;
    description: string | null;
    type: string;
    types: string[];
    available: boolean;
    unavailableReason: string | null;
    arguments: CatalogArgument[];
};

/** One node of the lazy-operators ExpressionBuilder definition format. */
export type NodeDefinition = {
    expression: string;
    version?: string;
    arguments: Record<string, unknown>;
};

export type Preset = {
    name: string;
    description: string;
    definition: NodeDefinition;
    inputs: Record<string, NodeDefinition>;
};

export type EvaluationError = {
    stage: 'definition' | 'input' | 'evaluation';
    input: string | null;
    exception: string;
    exceptionClass: string;
    message: string;
    path: string | null;
};

export type EvaluationResponse =
    | { result: { display: string; type: string; inputNames: string[] } }
    | { error: EvaluationError };
