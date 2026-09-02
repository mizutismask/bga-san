interface AutofitSettings {
    scaleStep?: number;
    minScale?: number;
}
interface AutofitWithObserverSettings extends AutofitSettings {
    rootElement?: HTMLElement;
}
/**
 * Auto-scale the content of divs with a `bga-autofit` class. Those divs should have a fixed width and height.
 * @param settings settings, width default : { scaleStep: 0.05, minScale: 0.1 }
 */
declare function init(settings?: AutofitWithObserverSettings): void;

declare const BgaAutofit: {
    init: typeof init;
};

export { BgaAutofit, init };
