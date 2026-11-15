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
        'recognizedAddress',
        'recapDelivery',
        'subtotal',
        'deliveryDistance',
        'deliveryCharge',
        'total',
        'quoteError',
    ];

    static values = {
        basePrice: Number,
        minimumPeople: Number,
        open: Boolean,
        openRecap: Boolean,
        quoteUrl: String,
        quoteToken: String,
        deliveryFee: Number,
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

    async showRecap(event) {
        const requiredFields = this.stepOneTarget.querySelectorAll(
            'input[required]:not([disabled])',
        );

        for (const field of requiredFields) {
            if (!field.checkValidity()) {
                field.reportValidity();

                return;
            }
        }

        const button = event.currentTarget;
        button.disabled = true;
        this.hideQuoteError();
        this.updateRecap();

        try {
            const quote = await this.fetchQuote();

            this.applyQuote(quote);
            Tab.getOrCreateInstance(this.recapTabTarget).show();
        } catch (error) {
            this.showQuoteError(
                error instanceof Error
                    ? error.message
                    : 'Le calcul de la livraison a échoué.',
            );
        } finally {
            button.disabled = false;
        }
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
        const menuTotal = unitPrice * people;
        const finalTotal = menuTotal + this.deliveryFeeValue;

        this.recapPeopleTarget.textContent = people.toString();
        this.subtotalTarget.textContent = this.formatCurrency(menuTotal);
        this.totalTarget.textContent = this.formatCurrency(finalTotal);
    }

    updateRecap() {
        this.updateTotal();
        this.recapAddressTarget.textContent = this.addressTarget.value;
        this.recapDeliveryTarget.textContent = this.deliveryTarget.value;
    }

    async fetchQuote() {
        const formData = new FormData();

        formData.append('_token', this.quoteTokenValue);
        formData.append('deliveryAddress', this.addressTarget.value);
        formData.append('numberOfPeople', this.peopleTarget.value);

        const response = await fetch(this.quoteUrlValue, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(
                payload.error
                    || 'Le calcul de la livraison a échoué.',
            );
        }

        return payload;
    }

    applyQuote(quote) {
        const distance = Number.parseFloat(quote.distanceKm);
        const deliveryFee = Number.parseFloat(quote.deliveryCharge);
        const menuPrice = Number.parseFloat(quote.menuPrice);
        const totalPrice = Number.parseFloat(quote.totalPrice);

        if (
            !Number.isFinite(distance)
            || !Number.isFinite(deliveryFee)
            || !Number.isFinite(menuPrice)
            || !Number.isFinite(totalPrice)
            || typeof quote.recognizedAddress !== 'string'
            || quote.recognizedAddress.trim() === ''
        ) {
            throw new Error('Le calcul de la livraison est invalide.');
        }

        this.deliveryFeeValue = deliveryFee;
        this.recognizedAddressTarget.textContent = quote.recognizedAddress;
        this.deliveryDistanceTarget.textContent = distance === 0
            ? 'Bordeaux'
            : `${this.formatNumber(distance)} km`;
        this.deliveryChargeTarget.textContent = this.formatCurrency(
            deliveryFee,
        );
        this.subtotalTarget.textContent = this.formatCurrency(menuPrice);
        this.totalTarget.textContent = this.formatCurrency(totalPrice);
    }

    showQuoteError(message) {
        this.quoteErrorTarget.textContent = message;
        this.quoteErrorTarget.classList.remove('d-none');
    }

    hideQuoteError() {
        this.quoteErrorTarget.textContent = '';
        this.quoteErrorTarget.classList.add('d-none');
    }

    formatCurrency(value) {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR',
        }).format(value);
    }

    formatNumber(value) {
        return new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(value);
    }
}
