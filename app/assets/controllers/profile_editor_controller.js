import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['actions', 'editButton', 'input', 'panel', 'tab'];
    static values = {
        editing: Boolean,
    };

    // Runs once when the controller is attached to the page.
    // Start with every field locked, then, if the server told us we're
    // already in "editing" mode (e.g. after a failed form submit), re-open
    // whichever panel is currently active so the user doesn't lose their place.
    connect() {
        this.setAllFieldsReadOnly();

        if (this.editingValue) {
            const activePanel = this.panelTargets.find((panel) => panel.classList.contains('active'));

            if (activePanel) {
                this.startEditing(activePanel, false);
            }
        }
    }

    // Called when the user clicks an "edit" button. Finds the panel that
    // button belongs to and switches it into editable mode.
    edit(event) {
        const panel = event.currentTarget.closest('[data-profile-editor-target="panel"]');

        if (panel) {
            this.startEditing(panel, true);
        }
    }

    // Called when the user clicks "cancel". Resets the form back to its
    // original values, locks all fields again, hides the save/cancel
    // buttons, shows the edit buttons again, and re-enables every tab.
    cancel() {
        this.element.reset();
        this.setAllFieldsReadOnly();
        this.actionsTarget.hidden = true;
        this.editButtonTargets.forEach((button) => {
            button.hidden = false;
        });
        this.tabTargets.forEach((tab) => {
            tab.disabled = false;
        });
    }

    // Switches a single panel into "edit" mode: unlocks only the fields
    // inside that panel, shows the save/cancel actions, hides the edit
    // buttons, and disables the other tabs so the user can't jump away
    // mid-edit. If focusFirstField is true, it also puts the cursor in the
    // panel's first input so the user can start typing right away.
    startEditing(panel, focusFirstField) {
        const activeTabId = panel.getAttribute('aria-labelledby');

        this.inputTargets.forEach((input) => {
            input.readOnly = !panel.contains(input);
        });
        this.actionsTarget.hidden = false;
        this.editButtonTargets.forEach((button) => {
            button.hidden = true;
        });
        this.tabTargets.forEach((tab) => {
            tab.disabled = tab.id !== activeTabId;
        });

        if (focusFirstField) {
            const firstInput = this.inputTargets.find((input) => panel.contains(input));
            firstInput?.focus();
        }
    }

    // Locks every field in the form so it's read-only by default. This is
    // the "resting" state before the user clicks edit on any panel.
    setAllFieldsReadOnly() {
        this.inputTargets.forEach((input) => {
            input.readOnly = true;
        });
    }
}
