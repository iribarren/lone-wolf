'use client';

/**
 * Character create/edit form (US5 — FR-021..FR-023). Every control comes
 * from the sheet structure the API hands back beside the characters: no
 * field key, label or type is known to this file.
 *
 * Conformity is judged by the domain validator, never here — the form
 * marks requirements for the eye only (`aria-required`, no native
 * `required`) so a breach reaches the API and comes back as a field-level
 * refusal, which is the behaviour US5 is about. A number field is a numeric
 * text input rather than `type="number"` for the same reason: a native
 * number input silently swallows "twelve" instead of letting the sheet say
 * "Hit points must be a number.".
 *
 * Structure discovery: `structureFields` only travels alongside existing
 * characters, so a campaign with none carries no shape at all. Rather than
 * offer a name and nothing else, the first refusal is used as the shape —
 * the sheet names every field it requires — and those inputs stay until the
 * save is accepted, at which point the real structure arrives with it.
 *
 * State is local and seeded from `character`; the parent swaps between
 * creating and editing by re-keying this component.
 */
import { useState, type FormEvent } from 'react';

import type { SheetFieldView, SheetViolation } from '@/components/characters/CharacterPanel';


import styles from './CharacterForm.module.css';
import Button from '@/components/ui/Button';
import Input from '@/components/ui/Input';
export type CharacterKind = 'pc' | 'npc';

export interface CharacterDraft {
    kind: CharacterKind;
    name: string;
    attributes: Record<string, unknown>;
}

export interface EditableCharacter {
    id: string;
    kind: string;
    name: string;
    attributes: Record<string, unknown>;
}

export interface CharacterFormProps {
    /** The system's sheet shape, as returned beside the campaign's characters. */
    fields?: SheetFieldView[] | null;
    /** The character being edited; absent when creating a new one. */
    character?: EditableCharacter | null;
    violations?: SheetViolation[];
    /** A refusal that names no field — e.g. a system with no sheet structure. */
    error?: string | null;
    /** The campaign's system has no sheet structure at all. */
    sheetless?: boolean;
    pending?: boolean;
    onSubmit: (draft: CharacterDraft) => void;
    onCancel?: () => void;
}

/** A field whose type the API has not told us yet (see the discovery note). */
const UNKNOWN_TYPE = 'unknown';

const NUMERIC = /^-?\d+(\.\d+)?$/;

function asText(value: unknown): string {
    return value === null || value === undefined ? '' : String(value);
}

/**
 * Number fields are coerced because the validator refuses numeric strings;
 * anything that does not read as a number is sent through untouched so the
 * message comes from the sheet rather than from a guess made here.
 */
function coerce(raw: string, type: string): unknown {
    if (type === 'number' || type === UNKNOWN_TYPE) {
        return NUMERIC.test(raw) ? Number(raw) : raw;
    }

    return raw;
}

function isRequired(field: SheetFieldView, kind: CharacterKind): boolean {
    return kind === 'pc' ? field.requiredForPc : field.requiredForNpc;
}

export default function CharacterForm({
    fields = [],
    character = null,
    violations = [],
    error = null,
    sheetless = false,
    pending = false,
    onSubmit,
    onCancel,
}: CharacterFormProps) {
    const editing = character !== null;
    const [kind, setKind] = useState<CharacterKind>(character?.kind === 'npc' ? 'npc' : 'pc');
    const [name, setName] = useState(character?.name ?? '');
    const [values, setValues] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            Object.entries(character?.attributes ?? {}).map(([key, value]) => [key, asText(value)]),
        ),
    );

    const structure = fields ?? [];
    // Keys the sheet has refused, plus anything already typed against them,
    // so a field does not vanish (losing its value) once its own violation
    // clears while another one stands.
    const discovered = [...violations.map((violation) => violation.field), ...Object.keys(values)]
        .filter((key, index, keys) => key !== 'kind' && key !== 'name' && keys.indexOf(key) === index)
        .map((key): SheetFieldView => ({
            key,
            label: key,
            type: UNKNOWN_TYPE,
            requiredForPc: false,
            requiredForNpc: false,
        }));
    const rendered = structure.length > 0 ? structure : discovered;

    const fieldViolations = new Map(
        violations
            .filter((violation) => rendered.some((field) => field.key === violation.field))
            .map((violation) => [violation.field, violation.message]),
    );
    const unnamed = violations.filter((violation) => !fieldViolations.has(violation.field));
    const notice = [error, ...unnamed.map((violation) => violation.message)]
        .filter((message): message is string => typeof message === 'string' && message !== '')
        .join(' ');

    function submit(event: FormEvent): void {
        event.preventDefault();

        const attributes: Record<string, unknown> = {};

        for (const field of rendered) {
            const raw = (values[field.key] ?? '').trim();

            // A blank is an absent attribute: whether that is a refusal is
            // the sheet's call, not this form's.
            if (raw !== '') {
                attributes[field.key] = coerce(raw, field.type);
            }
        }

        onSubmit({ kind, name: name.trim(), attributes });
    }

    if (sheetless) {
        return (
            <section aria-label="Add a character" className={styles.form}>
                <h3>Characters</h3>
                <p role="alert" data-testid="character-form-error" className={styles.error}>
                    {error ?? 'This game system defines no character sheet, so characters cannot be added to it.'}
                </p>
                {onCancel && (
                    <Button variant="ghost" onClick={onCancel}>
                        Close
                    </Button>
                )}
            </section>
        );
    }

    return (
        <form
            onSubmit={submit}
            aria-label={editing ? `Edit ${character.name}` : 'Add a character'}
            className={styles.form}
        >
            <h3>{editing ? `Edit ${character.name}` : 'Add a character'}</h3>

            <div>
                <label htmlFor="character-name">Name</label>{' '}
                <Input
                    id="character-name"
                    type="text"
                    value={name}
                    disabled={pending}
                    onChange={(event) => setName(event.target.value)}
                />
            </div>

            <div>
                {editing ? (
                    <p data-testid="character-form-kind">Kind: {kind.toUpperCase()} — a character&apos;s kind cannot change.</p>
                ) : (
                    <>
                        <label htmlFor="character-kind">Kind</label>{' '}
                        <select
                            id="character-kind"
                            value={kind}
                            disabled={pending}
                            onChange={(event) => setKind(event.target.value === 'npc' ? 'npc' : 'pc')}
                        >
                            <option value="pc">PC</option>
                            <option value="npc">NPC</option>
                        </select>
                    </>
                )}
            </div>

            {rendered.map((field) => {
                const inputId = `character-field-${field.key}`;
                const message = fieldViolations.get(field.key);
                const required = isRequired(field, kind);
                const value = values[field.key] ?? '';
                const change = (next: string) =>
                    setValues((current) => ({ ...current, [field.key]: next }));

                return (
                    <div key={field.key} data-testid={inputId}>
                        <label htmlFor={inputId}>
                            {field.label}
                            {required ? ' (required)' : ''}
                        </label>{' '}
                        {field.type === 'select' && Array.isArray(field.options) ? (
                            <select
                                id={inputId}
                                value={value}
                                disabled={pending}
                                aria-required={required}
                                aria-invalid={message === undefined ? undefined : true}
                                aria-describedby={message === undefined ? undefined : `field-error-${field.key}`}
                                onChange={(event) => change(event.target.value)}
                            >
                                <option value="">—</option>
                                {field.options.map((option) => (
                                    <option key={option} value={option}>
                                        {option}
                                    </option>
                                ))}
                            </select>
                        ) : (
                            <Input
                                id={inputId}
                                type="text"
                                inputMode={field.type === 'number' ? 'numeric' : undefined}
                                value={value}
                                disabled={pending}
                                aria-required={required}
                                aria-invalid={message === undefined ? undefined : true}
                                aria-describedby={message === undefined ? undefined : `field-error-${field.key}`}
                                onChange={(event) => change(event.target.value)}
                            />
                        )}
                        {message !== undefined && (
                            <p
                                id={`field-error-${field.key}`}
                                role="alert"
                                data-testid={`field-error-${field.key}`}
                                className={styles.error}
                            >
                                {message}
                            </p>
                        )}
                    </div>
                );
            })}

            {notice !== '' && (
                <p role="alert" data-testid="character-form-error" className={styles.error}>
                    {notice}
                </p>
            )}

            <Button
                type="submit"
                variant="primary"
                disabled={name.trim() === ''}
                pending={pending}
                pendingLabel="Saving…"
            >
                {editing ? 'Save character' : 'Add character'}
            </Button>{' '}
            {onCancel && (
                <Button variant="ghost" disabled={pending} onClick={onCancel}>
                    Cancel
                </Button>
            )}
        </form>
    );
}
