import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    connect() {
        this.modal = Modal.getOrCreateInstance(this.element);
        this.beforeCache = this.closeBeforeCache.bind(this);
        document.addEventListener('turbo:before-cache', this.beforeCache);
        this.modal.show();
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.beforeCache);
        this.modal?.dispose();
    }

    closeBeforeNavigation() {
        this.modal?.hide();
    }

    closeBeforeCache() {
        this.modal?.hide();
        this.element.classList.remove('show');
        this.element.style.removeProperty('display');
        this.element.setAttribute('aria-hidden', 'true');
        this.element.removeAttribute('aria-modal');
        this.element.removeAttribute('role');

        document.querySelectorAll('.modal-backdrop').forEach((backdrop) => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('overflow');
        document.body.style.removeProperty('padding-right');
    }
}
