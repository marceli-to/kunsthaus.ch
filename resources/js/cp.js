import ImagePreview from './components/fieldtypes/ImagePreview.vue';
import ReadonlyValue from './components/fieldtypes/ReadonlyValue.vue';

Statamic.booting(() => {
    Statamic.$components.register('image_preview-fieldtype', ImagePreview);
    Statamic.$components.register('readonly_value-fieldtype', ReadonlyValue);
});
