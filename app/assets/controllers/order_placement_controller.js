import { Controller } from '@hotwired/stimulus';
import { Modal, Tab } from 'bootstrap';

export default class extends Controller {
    static targets = [
        'stepOne',
        'detailsTab',
        'recapTab',
        'people',
        'address',
        'delivery',
        'recapPeople',
        'recapAddress',
        'recapDelivery',
        'subtotal',
        'total',
    ];

    static values = {
        basePrice: Number,
        minimumPeople: Number,
        open: Boolean,
        openRecap: Boolean,
    };

    connect() {
        if (this.hasPeopleTarget) {
            this.updateTotal();
        }

        if (this.openRecapValue && this.hasRecapTabTarget) {
            Tab.getOrCreateInstance(this.recapTabTarget).show();
        }

        if (this.openValue) {
            Modal.getOrCreateInstance(this.element).show();
        }
    }

    showRecap() {
        const requiredFields = this.stepOneTarget.querySelectorAll(
            'input[required]:not([disabled])',
        );

        for (const field of requiredFields) {
            if (!field.checkValidity()) {
                field.reportValidity();

                return;
            }
        }

        this.updateRecap();
        Tab.getOrCreateInstance(this.recapTabTarget).show();
    }

    showForm() {
        Tab.getOrCreateInstance(this.detailsTabTarget).show();
    }

    updateTotal() {
        const people = Math.max(
            Number.parseInt(this.peopleTarget.value, 10)
                || this.minimumPeopleValue,
            this.minimumPeopleValue,
        );

        const unitPrice = this.basePriceValue / this.minimumPeopleValue;
        const total = unitPrice * people;

        this.recapPeopleTarget.textContent = people.toString();
        this.subtotalTarget.textContent = new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR',
        }).format(total);
        this.totalTarget.textContent = new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR',
        }).format(total);
    }

    updateRecap() {
        this.updateTotal();
        this.recapAddressTarget.textContent = this.addressTarget.value;
        this.recapDeliveryTarget.textContent = this.deliveryTarget.value;
    }
}
