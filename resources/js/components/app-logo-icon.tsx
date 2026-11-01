import type { SVGAttributes } from 'react';

/** The Canco Cares heart. Uses the surrounding text colour (fill-current). */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path d="M12 21s-7.6-4.7-9.7-9.5C.8 8.1 2.6 4.4 6.3 4.4c2 0 3.6 1 4.7 2.6l1 1.4 1-1.4c1.1-1.6 2.7-2.6 4.7-2.6 3.7 0 5.5 3.7 4 7.1C19.6 16.3 12 21 12 21z" />
        </svg>
    );
}
