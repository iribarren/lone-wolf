'use client';

/**
 * Dynamic character panel (T082, US5 — FR-022..FR-025). Sheets render from
 * the structure metadata the API returns — no hardcoded fields anywhere.
 * Pure presentational so states are directly testable (T083).
 */
export interface SheetFieldView {
    key: string;
    label: string;
    type: string;
    requiredForPc: boolean;
    requiredForNpc: boolean;
    options?: string[];
}

export interface CharacterPanelCharacter {
    id: string;
    kind: string;
    name: string;
    attributes: Record<string, unknown>;
    reviewStatus: 'clean' | 'flagged_for_review';
    driftIssues: string[];
    structureFields?: SheetFieldView[] | null;
}

export interface SheetViolation {
    field: string;
    message: string;
}

export interface CharacterPanelProps {
    characters: CharacterPanelCharacter[];
    loading?: boolean;
    violations?: SheetViolation[];
}

function formatValue(value: unknown): string {
    if (typeof value === 'boolean') {
        return value ? 'yes' : 'no';
    }

    return String(value);
}

export default function CharacterPanel({ characters, loading = false, violations = [] }: CharacterPanelProps) {
    if (loading) {
        return (
            <section aria-busy="true" data-testid="characters-loading">
                <p>Loading characters…</p>
            </section>
        );
    }

    if (characters.length === 0) {
        return (
            <section aria-label="Characters" data-testid="characters-empty">
                <p>No characters yet.</p>
            </section>
        );
    }

    return (
        <section aria-label="Characters" data-testid="character-panel">
            {violations.length > 0 ? (
                <ul role="alert" data-testid="sheet-violations">
                    {violations.map((violation) => (
                        <li key={`${violation.field}:${violation.message}`}>
                            <strong>{violation.field}</strong>: {violation.message}
                        </li>
                    ))}
                </ul>
            ) : null}

            <ul data-testid="characters-list">
                {characters.map((character) => (
                    <li key={character.id} data-testid={`character-${character.name}`}>
                        <header>
                            <h3>{character.name}</h3>{' '}
                            <small>{character.kind.toUpperCase()}</small>{' '}
                            {character.reviewStatus === 'flagged_for_review' ? (
                                <span role="status" title={character.driftIssues.join('; ')} data-testid={`drift-badge-${character.name}`}>
                                    ⚑ flagged for review
                                </span>
                            ) : null}
                        </header>

                        <dl>
                            {(character.structureFields ?? []).map((field) => {
                                const raw = character.attributes[field.key];

                                return (
                                    <div key={field.key} data-testid={`field-${field.key}`}>
                                        <dt>{field.label}</dt>
                                        <dd>
                                            {raw === undefined ? (
                                                <em>—</em>
                                            ) : field.type === 'select' && Array.isArray(field.options) && field.options.length > 0 ? (
                                                `${formatValue(raw)} of ${field.options.join('/')}`
                                            ) : (
                                                formatValue(raw)
                                            )}
                                        </dd>
                                    </div>
                                );
                            })}
                        </dl>

                        {character.reviewStatus === 'flagged_for_review' ? (
                            <ul data-testid={`drift-issues-${character.name}`}>
                                {character.driftIssues.map((issue) => (
                                    <li key={issue}>{issue}</li>
                                ))}
                            </ul>
                        ) : null}
                    </li>
                ))}
            </ul>
        </section>
    );
}
