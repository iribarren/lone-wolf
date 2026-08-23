import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import AdvanceActions from '@/components/campaign/AdvanceActions';
import type { ApiSchemas } from '@/lib/api/client';

type StageAction = ApiSchemas['StageActionResource'];

const advanceAction: StageAction = {
    kind: 'advance',
    toStageId: 'Sequel',
    toStageName: 'Sequel',
    prompt: 'Move on to the Sequel',
};

const concludeAction: StageAction = {
    kind: 'conclude',
    toStageId: null,
    toStageName: null,
    prompt: 'Conclude campaign',
};

describe('AdvanceActions', () => {
    it('renders one control per suggested action and advances with the target stage id', () => {
        const onAdvance = vi.fn();
        const onConclude = vi.fn();

        render(
            <AdvanceActions actions={[advanceAction, concludeAction]} onAdvance={onAdvance} onConclude={onConclude} />,
        );

        fireEvent.click(screen.getByRole('button', { name: 'Move on to the Sequel' }));
        expect(onAdvance).toHaveBeenCalledWith('Sequel');
        expect(onConclude).not.toHaveBeenCalled();
    });

    it('routes conclude actions to onConclude', () => {
        const onAdvance = vi.fn();
        const onConclude = vi.fn();

        render(<AdvanceActions actions={[concludeAction]} onAdvance={onAdvance} onConclude={onConclude} />);

        fireEvent.click(screen.getByRole('button', { name: 'Conclude campaign' }));
        expect(onConclude).toHaveBeenCalledOnce();
    });

    it('shows refusal feedback with the legal alternatives after an illegal move', () => {
        render(
            <AdvanceActions
                actions={[]}
                refusal={{ detail: 'Illegal transition.', legalAlternatives: ['Scene', 'Interlude'] }}
                onAdvance={vi.fn()}
                onConclude={vi.fn()}
            />,
        );

        const banner = screen.getByTestId('refusal-banner');
        expect(banner).toHaveTextContent('Illegal transition.');
        expect(banner).toHaveTextContent('Legal moves: Scene, Interlude');
    });

    it('disables every control while a transition is pending', () => {
        render(
            <AdvanceActions
                actions={[advanceAction]}
                disabled
                pending
                onAdvance={vi.fn()}
                onConclude={vi.fn()}
            />,
        );

        expect(screen.getByRole('button', { name: 'Move on to the Sequel' })).toBeDisabled();
    });
});
