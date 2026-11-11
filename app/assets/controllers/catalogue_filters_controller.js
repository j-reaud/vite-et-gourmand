import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['active', 'counter', 'form', 'panel', 'summary', 'toggle'];

    connect() {
        this.timeout = null;
    }

    disconnect() {
        window.clearTimeout(this.timeout);
    }

    submit() {
        this.syncActiveFilters();
        this.formTarget.requestSubmit();
    }

    scheduleSubmit() {
        this.syncActiveFilters();
        window.clearTimeout(this.timeout);
        this.timeout = window.setTimeout(() => this.submit(), 350);
    }

    toggle() {
        const isOpen = this.panelTarget.classList.toggle('is-open');
        this.toggleTarget.setAttribute('aria-expanded', String(isOpen));
    }

    syncResults() {
        const count = this.element.querySelectorAll('#menu-results .catalogue-card').length;
        const plural = count > 1 ? 's' : '';

        this.summaryTarget.textContent = `${count} menu${plural} disponible${plural}`;
    }

    syncActiveFilters() {
        const data = new FormData(this.formTarget);
        const minPrice = data.get('minPrice')?.trim();
        const maxPrice = data.get('maxPrice')?.trim();
        const theme = data.get('theme')?.trim();
        const diet = data.get('diet')?.trim();
        const people = data.get('people')?.trim();
        const labels = [];

        if (minPrice || maxPrice) {
            labels.push(`${minPrice || 0}–${maxPrice || '∞'} €`);
        }
        if (theme) {
            labels.push(theme);
        }
        if (diet) {
            labels.push(diet);
        }
        if (people) {
            labels.push(`Pour ${people} pers.`);
        }

        this.activeTarget.replaceChildren();

        for (const label of labels.length > 0 ? labels : ['Aucun filtre']) {
            const chip = document.createElement('span');
            chip.textContent = label;
            this.activeTarget.append(chip);
        }

        const plural = labels.length > 1 ? 's' : '';
        this.counterTarget.textContent = `${labels.length} actif${plural}`;
    }
}
