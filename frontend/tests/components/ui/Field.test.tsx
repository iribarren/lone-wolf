import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import Input from '@/components/ui/Input';
import Textarea from '@/components/ui/Textarea';

/**
 * The app associates every control with an explicit <label htmlFor>. The
 * primitives must forward id and name untouched or those associations break --
 * and the E2E suite selects entirely by accessible name.
 */
describe('Input', () => {
    it('stays associated with an external label', () => {
        render(
            <>
                <label htmlFor="dice-notation">Dice notation</label>
                <Input id="dice-notation" name="notation" placeholder="e.g. 1d20+5" />
            </>,
        );

        const field = screen.getByLabelText('Dice notation');
        expect(field).toHaveAttribute('name', 'notation');
        expect(field).toHaveAttribute('placeholder', 'e.g. 1d20+5');
    });

    it('forwards the field-level error wiring', () => {
        render(
            <>
                <label htmlFor="hp">Hit points</label>
                <Input id="hp" aria-describedby="field-error-hp" aria-invalid />
                <p id="field-error-hp">Hit points must be a number.</p>
            </>,
        );

        const field = screen.getByLabelText('Hit points');
        expect(field).toHaveAttribute('aria-describedby', 'field-error-hp');
        expect(field).toHaveAttribute('aria-invalid', 'true');
    });
});

describe('Textarea', () => {
    it('stays associated with an external label and keeps its rows', () => {
        render(
            <>
                <label htmlFor="oracle-interpretation">Interpretation</label>
                <Textarea id="oracle-interpretation" name="interpretation" rows={2} />
            </>,
        );

        const field = screen.getByLabelText('Interpretation');
        expect(field).toHaveAttribute('name', 'interpretation');
        expect(field).toHaveAttribute('rows', '2');
    });
});
