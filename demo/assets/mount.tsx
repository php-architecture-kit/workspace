import type { ComponentType } from 'react';
import { createRoot } from 'react-dom/client';

export type ComponentMap = Record<string, ComponentType<any>>;

/**
 * Mounts every `<div data-react-component="Name" data-props="{…}">` rendered by Twig.
 */
export function mountComponents(components: ComponentMap): void {
    document.querySelectorAll<HTMLElement>('[data-react-component]').forEach((element) => {
        const name = element.dataset.reactComponent ?? '';
        const Component = components[name];

        if (Component === undefined) {
            console.error(`React component "${name}" is not registered.`);

            return;
        }

        const props = JSON.parse(element.dataset.props ?? '{}');

        createRoot(element).render(<Component {...props} />);
    });
}
