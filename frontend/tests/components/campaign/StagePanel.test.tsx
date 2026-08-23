import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import StagePanel from '@/components/campaign/StagePanel';

describe('StagePanel', () => {
    it('renders the stage name and its guidance card', () => {
        render(<StagePanel stageName="Scene" guidance="Set the scene and ask the oracle." />);

        expect(screen.getByRole('heading', { name: 'Scene' })).toBeInTheDocument();
        expect(screen.getByText('Set the scene and ask the oracle.')).toBeInTheDocument();
    });

    it('shows a busy placeholder while loading', () => {
        render(<StagePanel stageName="" guidance="" loading />);

        expect(screen.getByTestId('stage-panel-loading')).toBeInTheDocument();
    });
});
