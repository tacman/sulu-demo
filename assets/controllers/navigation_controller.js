import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['links', 'toggle'];

    connect() {
        this.updateScrollState = this.updateScrollState.bind(this);
        window.addEventListener('scroll', this.updateScrollState, { passive: true });
        this.updateScrollState();
    }

    disconnect() {
        window.removeEventListener('scroll', this.updateScrollState);
    }

    toggle() {
        const isOpen = this.linksTarget.style.display === 'flex';
        this.linksTarget.style.display = isOpen ? 'none' : 'flex';
        this.toggleTarget.classList.toggle('icon--times', !isOpen);
        this.toggleTarget.classList.toggle('icon--menu', isOpen);
    }

    updateScrollState() {
        this.element.classList.toggle('navbar--scroll', window.scrollY > 0);
    }
}
