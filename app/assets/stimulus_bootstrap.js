// This file is what wires up Stimulus (the JS framework) for the whole app.
// Symfony UX auto-generates most of this normally; manually-added
// controllers like ProfileEditorController need to be registered here by
// hand so Stimulus knows about them.
import { startStimulusApp } from '@symfony/stimulus-bundle';
import CatalogueFiltersController from './controllers/catalogue_filters_controller.js';
import ProfileEditorController from './controllers/profile_editor_controller.js';
import OrderFiltersController from './controllers/order_filters_controller.js';
import OrderPlacementController from './controllers/order_placement_controller.js';
import EmployeeCreateModalController from './controllers/employee_create_modal_controller.js';
import ContactSuccessController from './controllers/contact_success_controller.js';

// Boots the Stimulus application for this page.
const app = startStimulusApp();

app.register('catalogue-filters', CatalogueFiltersController);

// Makes the controller available under the name "profile-editor", which is
// what you reference in HTML via data-controller="profile-editor".
app.register('profile-editor', ProfileEditorController);
app.register('order-filters', OrderFiltersController);
app.register('order-placement', OrderPlacementController);
app.register('employee-create-modal', EmployeeCreateModalController);
app.register('contact-success', ContactSuccessController);
