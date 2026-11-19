import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    connect() {
        this.modal = Modal.getOrCreateInstance(this.element);
        this.modal.show();
    }

    disconnect() {
        this.modal?.dispose();
    }
}
