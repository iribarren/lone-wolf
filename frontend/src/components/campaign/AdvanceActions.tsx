'use client';

/**
 * Advance controls rendered from the stage's suggestedActions (T051, FR-014)
 * plus the refusal feedback banner for illegal transitions (FR-016).
 */
import type { ApiSchemas } from '@/lib/api/client';


import styles from './AdvanceActions.module.css';
import Banner from '@/components/ui/Banner';
import Button from '@/components/ui/Button';
type StageAction = ApiSchemas['StageActionResource'];

export interface RefusalFeedback {
    detail: string;
    legalAlternatives: string[];
}

export interface AdvanceActionsProps {
    actions: StageAction[];
    disabled?: boolean;
    pending?: boolean;
    refusal?: RefusalFeedback | null;
    onAdvance: (toStageId: string) => void;
    onConclude: () => void;
}

function actionLabel(action: StageAction): string {
    if (action.kind === 'conclude') {
        return action.prompt ?? 'Conclude campaign';
    }

    return action.prompt ?? `Advance to ${action.toStageName ?? action.toStageId}`;
}

export default function AdvanceActions({
    actions,
    disabled = false,
    pending = false,
    refusal = null,
    onAdvance,
    onConclude,
}: AdvanceActionsProps) {
    return (
        <div className={styles.actions}>
            <h3>What next?</h3>

            {actions.length === 0 && <p>No actions available at this stage.</p>}

            <ul className={styles.list}>
                {actions.map((action) => (
                    <li key={`${action.kind}:${action.toStageId ?? 'terminal'}`}>
                        <Button
                            variant="primary"
                            disabled={disabled || pending}
                            onClick={() => {
                                if (action.kind === 'conclude') {
                                    onConclude();
                                } else if (action.toStageId) {
                                    onAdvance(action.toStageId);
                                }
                            }}
                        >
                            {actionLabel(action)}
                        </Button>
                    </li>
                ))}
            </ul>

            {refusal && (
                <Banner
                    variant="refusal"
                    role="alert"
                    data-testid="refusal-banner"
                    className={styles.refusal}
                >
                    <p>{refusal.detail}</p>
                    {refusal.legalAlternatives.length > 0 && (
                        <p>
                            Legal moves:{' '}
                            {refusal.legalAlternatives.join(', ')}
                        </p>
                    )}
                </Banner>
            )}
        </div>
    );
}
