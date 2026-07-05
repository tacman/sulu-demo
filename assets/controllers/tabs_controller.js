import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['tab', 'panel'];
    static values = { hash: Boolean };

    connect() {
        const initialIndex = this.hashValue ? this.indexFromHash() : -1;
        this.select(initialIndex >= 0 ? initialIndex : 0, false);
    }

    choose(event) {
        event.preventDefault();
        this.select(this.tabTargets.indexOf(event.currentTarget));
    }

    select(index, updateHash = true) {
        this.tabTargets.forEach((tab, tabIndex) => {
            const active = tabIndex === index;
            tab.disabled = active;
            tab.classList.toggle(`${this.baseClass(tab)}--active`, active);
        });

        this.panelTargets.forEach((panel, panelIndex) => {
            panel.hidden = panelIndex !== index;
        });

        if (updateHash && this.hashValue) {
            const name = this.tabTargets[index]?.dataset.name || '';
            if (name) {
                history.replaceState(null, '', `#${encodeURIComponent(name)}`);
            }
        }
    }

    indexFromHash() {
        const hash = decodeURIComponent(window.location.hash.replace(/^#/, ''));
        return this.tabTargets.findIndex((tab) => tab.dataset.name === hash);
    }

    baseClass(tab) {
        return (tab.className || '').split(' ').find((className) => className.length > 0) || 'active';
    }
}
