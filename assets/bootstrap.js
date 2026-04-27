import { startStimulusApp } from '@symfony/stimulus-bundle';

// Démarre Stimulus → charge automatiquement les contrôleurs
// déclarés dans assets/controllers.json (notamment le map_controller
// du bundle symfony/ux-leaflet-map qui rend la carte).
const app = startStimulusApp();

// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
