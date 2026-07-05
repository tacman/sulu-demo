import { Controller } from '@hotwired/stimulus';

const units = [
    ['year', 31536000],
    ['month', 2592000],
    ['week', 604800],
    ['day', 86400],
    ['hour', 3600],
    ['minute', 60],
    ['second', 1],
];

export default class extends Controller {
    static values = { date: String };

    connect() {
        const date = new Date(this.dateValue);
        if (Number.isNaN(date.getTime())) {
            return;
        }

        const seconds = Math.round((date.getTime() - Date.now()) / 1000);
        const [unit, divisor] = units.find(([, value]) => Math.abs(seconds) >= value) || ['second', 1];
        const locale = document.documentElement.lang || 'en';

        this.element.textContent = new Intl.RelativeTimeFormat(locale, { numeric: 'auto' }).format(
            Math.round(seconds / divisor),
            unit,
        );
    }
}
