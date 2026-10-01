interface HelpPopinSettings {
    /**
     * The popin title.
     */
    title: string;

    /**
     * The HTML content to put on the popin.
     */
    html?: string;

    /**
     * A function to call when the popin is created.
     */
    onPopinCreated?: (popinContentElement: HTMLElement) => void;

    /**
     * Classes to add to the button, separated by spaces
     */
    buttonExtraClasses?: string;

    /**
     * Css background property for the help background.
     * Default 'black'.
     */
    buttonBackground?: string;

    /**
     * Css background property for the "?" color.
     * Default 'white'.
     */
    buttonColor?: string;
}

interface HelpExpandableSettings {
    /**
     * Content of the button, when folded
     */
    foldedHtml?: string;

    /**
     * Classes to add to the folded content
     */
    foldedContentExtraClasses?: string;

    /**
     * Content of the button, when unfolded
     */
    unfoldedHtml?: string;

    /**
     * Classes to add to the unfolded content
     */
    unfoldedContentExtraClasses?: string;

    /**
     * Classes to add to the button, separated by spaces
     */
    buttonExtraClasses?: string;

    /**
     * Css size of the width when expanded.
     * Default 'auto'. Note that auto will not provide a expand animation, you need to set a fixed size (in px for example) to make it work.
     */
    expandedWidth?: string;

    /**
     * Css size of the height when expanded.
     * Default 'auto'. Note that auto will not provide a expand animation, you need to set a fixed size (in px for example) to make it work.
     */
    expandedHeight?: string;

    /**
     * Css size of the radius when expanded.
     * Default '10px'.
     */
    expandedRadius?: string;

    /**
     * If the help button is folded by default. 
     * If you set it to false, you really should set localStorageFoldedKey so players don't have to close it everytime if they are not interested.
     * Default true.
     */
    defaultFolded?: boolean;

    /**
     * The key used to persist the folded state on localStorage.
     * Default (unset) is no storage.
     */
    localStorageFoldedKey?: string;
}

export abstract class BgaHelpButton {

    public abstract add(toElement: HTMLElement): void;
}

export class BgaHelpPopinButton extends BgaHelpButton {
    constructor(private settings: HelpPopinSettings) {
        super();
    }

    public add(toElement: HTMLElement) {
        const button = document.createElement('button');
        button.classList.add('bga-help_button', 'bga-help_popin-button', ...(this.settings.buttonExtraClasses ? this.settings.buttonExtraClasses.split(/\s+/g) : []));
        button.innerHTML = `?`;
        if (this.settings.buttonBackground) {
            button.style.setProperty('--background', this.settings.buttonBackground);
        }
        if (this.settings.buttonColor) {
            button.style.setProperty('--color', this.settings.buttonColor);
        }

        toElement.appendChild(button);
        button.addEventListener('click', () => this.showHelp());
    }

    protected showHelp() {
        const popinDialog = new (window as any).ebg.popindialog();
        popinDialog.create('bgaHelpDialog');
        popinDialog.setTitle(this.settings.title);
        popinDialog.setContent(`<div id="help-dialog-content">${this.settings.html ?? ''}</div>`);

        this.settings.onPopinCreated?.(document.getElementById('help-dialog-content'));

        popinDialog.show();
    }
}

export class BgaHelpExpandableButton extends BgaHelpButton {
    constructor(private settings: HelpExpandableSettings) {
        super();
    }

    public add(toElement: HTMLElement) {
        let folded = this.settings.defaultFolded ?? true;
        if (this.settings.localStorageFoldedKey) {
            const localStorageValue = localStorage.getItem(this.settings.localStorageFoldedKey);
            if (localStorageValue) {
                folded = localStorageValue == 'true';
            }
        }

        const button = document.createElement('button');
        button.dataset.folded = folded.toString();
        button.classList.add('bga-help_button', 'bga-help_expandable-button', ...(this.settings.buttonExtraClasses ? this.settings.buttonExtraClasses.split(/\s+/g) : []));
        button.innerHTML = `
            <div class="bga-help_folded-content ${(this.settings.foldedContentExtraClasses ?? '').split(/\s+/g)}">${this.settings.foldedHtml ?? ''}</div>
            <div class="bga-help_unfolded-content  ${(this.settings.unfoldedContentExtraClasses ?? '').split(/\s+/g)}">${this.settings.unfoldedHtml ?? ''}</div>
        `;
        button.style.setProperty('--expanded-width', this.settings.expandedWidth ?? 'auto');
        button.style.setProperty('--expanded-height', this.settings.expandedHeight ?? 'auto');
        button.style.setProperty('--expanded-radius', this.settings.expandedRadius ?? '10px');

        toElement.appendChild(button);
        button.addEventListener('click', () => {
            button.dataset.folded = button.dataset.folded == 'true' ? 'false' : 'true';
            if (this.settings.localStorageFoldedKey) {
                localStorage.setItem(this.settings.localStorageFoldedKey, button.dataset.folded);
            }
        });
    }
}

interface HelpSettings {
    /**
     * The list of buttons to show on the bottom left corner of the screen.
     */
    buttons: BgaHelpButton[];
}

export class HelpManager {

    constructor(private game: any, settings: HelpSettings) {
        if (!settings?.buttons) {
            throw new Error('HelpManager need a `buttons` list in the settings.');
        }
        const leftSide = document.getElementById('left-side');
        const buttons = document.createElement('div');
        buttons.id = `bga-help_buttons`;
        leftSide.appendChild(buttons);
        settings.buttons.forEach(button => button.add(buttons));
    }
}