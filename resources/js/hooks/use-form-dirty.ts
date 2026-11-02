import { useCallback, useEffect, useRef, useState } from 'react';
import type { FormEvent, RefObject } from 'react';

/**
 * Tracks whether the person has touched a form, to show "Unsaved changes" in the action bar. Spread
 * `bindings` on the <Form>: native inputs bubble `input`/`change` events up to it. Call `touch()` for
 * edits that raise no DOM event (chips, list items) and `clear()` after a successful save.
 */
export function useFormDirty() {
    const [dirty, setDirty] = useState(false);
    // React events also bubble out of portals (dialogs); only edits to the form's own fields count.
    const mark = useCallback((event: FormEvent<HTMLElement>) => {
        if (
            event.target instanceof Node &&
            event.currentTarget.contains(event.target)
        ) {
            setDirty(true);
        }
    }, []);
    const touch = useCallback(() => setDirty(true), []);
    const clear = useCallback(() => setDirty(false), []);

    return { dirty, touch, clear, bindings: { onInput: mark, onChange: mark } };
}

/**
 * For a hidden input that carries a value set by script (a rich-text editor, a picked image):
 * returns a ref for it and fires a bubbling `input` event whenever `value` changes after mount, so
 * the surrounding form notices the edit.
 */
export function useInputNotifier(
    value: string,
): RefObject<HTMLInputElement | null> {
    const ref = useRef<HTMLInputElement>(null);
    const previous = useRef(value);

    useEffect(() => {
        if (previous.current === value) {
            return;
        }

        previous.current = value;
        ref.current?.dispatchEvent(new Event('input', { bubbles: true }));
    }, [value]);

    return ref;
}
