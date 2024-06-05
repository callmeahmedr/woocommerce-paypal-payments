import GooglepayButton from "./GooglepayButton";
import widgetBuilder from "../../../ppcp-button/resources/js/modules/Renderer/WidgetBuilder";
import PreviewButton from "../../../ppcp-button/resources/js/modules/Renderer/PreviewButton";
import PreviewButtonManager from "../../../ppcp-button/resources/js/modules/Renderer/PreviewButtonManager";

/**
 * Button manager instance; we usually only need a single instance of this object.
 *
 * @see buttonManager()
 */
let managerInstance = null;

/**
 * Default attributes for new buttons.
 */
const defaultAttributes = {
    button: {
        style: {
            type: 'pay',
            color: 'black',
            language: 'en'
        }
    }
};

/**
 * Accessor that creates and returns a single PreviewButtonManager instance.
 */
const buttonManager = () => {
    if (!managerInstance) {
        managerInstance = new GooglePayPreviewButtonManager();
    }

    return managerInstance;
}


// ----------------------------------------------------------------------------


/**
 * A single GooglePay preview button instance.
 */
class GooglePayPreviewButton extends PreviewButton {
    constructor(args) {
        super(args);

        this.selector = `${args.selector}GooglePay`
    }

    createNewWrapper() {
        const element = super.createNewWrapper();
        element.addClass('ppcp-button-googlepay');
        return element;
    }

    createButton() {
        const button = new GooglepayButton(
            'preview',
            null,
            this.buttonConfig,
            this.ppcpConfig,
        );

        button.init(this.configResponse);

        return button;
    }
}


// ----------------------------------------------------------------------------


/**
 * Manages all GooglePay preview buttons on this page.
 */
class GooglePayPreviewButtonManager extends PreviewButtonManager {
    constructor() {
        const args = {
            // WooCommerce configuration object.
            buttonConfig: window.wc_ppcp_googlepay_admin,
            // Internal widgetBuilder instance.
            widgetBuilder,
            // Default button styles.
            defaultAttributes
        };

        super(args);

        this.methodName = 'GooglePay';
    }

    /**
     * Responsible for fetching and returning the PayPal configuration object for this payment
     * method.
     *
     * @return {Promise<{}>}
     */
    async fetchConfig() {
        const apiMethod = this.widgetBuilder?.paypal?.Googlepay()?.config

        if (!apiMethod) {
            this.error('configuration object cannot be retrieved from PayPal');
            return {};
        }

        return await apiMethod();
    }

    /**
     * This method is responsible for creating a new PreviewButton instance and returning it.
     *
     * @param {string} wrapperId - CSS ID of the wrapper element.
     * @return {GooglePayPreviewButton}
     */
    createButtonInst(wrapperId) {
        return new GooglePayPreviewButton({
            selector: wrapperId,
            configResponse: this.configResponse,
            defaultAttributes: this.defaultAttributes
        });
    }
}

alert('GPay Boot Admin')
// Initialize the preview button manager.
buttonManager()

// todo - Expose button manager for testing. Remove this!
window.gpay = buttonManager()


/**

(function ({
   buttonConfig,
   jQuery
}) {

    let googlePayConfig;
    let buttonQueue = [];
    let activeButtons = {};
    let bootstrapped = false;

    // React to PayPal config changes.
    jQuery(document).on('ppcp_paypal_render_preview', (ev, ppcpConfig) => {
        if (bootstrapped) {
            createButton(ppcpConfig);
        } else {
            buttonQueue.push({
                ppcpConfig: JSON.parse(JSON.stringify(ppcpConfig))
            });
        }
    });

    // React to GooglePay config changes.
    jQuery([
        '#ppcp-googlepay_button_enabled',
        '#ppcp-googlepay_button_type',
        '#ppcp-googlepay_button_color',
        '#ppcp-googlepay_button_language',
        '#ppcp-googlepay_button_shipping_enabled'
    ].join(',')).on('change', () => {
        for (const [selector, ppcpConfig] of Object.entries(activeButtons)) {
            createButton(ppcpConfig);
        }
    });

    // Maybe we can find a more elegant reload method when transitioning from styling modes.
    jQuery([
        '#ppcp-smart_button_enable_styling_per_location'
    ].join(',')).on('change', () => {
        setTimeout(() => {
            for (const [selector, ppcpConfig] of Object.entries(activeButtons)) {
                createButton(ppcpConfig);
            }
        }, 100);
    });

    const shouldDisplayPreviewButton = function() {
        // TODO - original condition, which is wrong.
        return jQuery('#ppcp-googlepay_button_enabled').is(':checked');
    }

    const applyConfigOptions = function (buttonConfig) {
        buttonConfig.button = buttonConfig.button || {};
        buttonConfig.button.style = buttonConfig.button.style || {};
        buttonConfig.button.style.type = jQuery('#ppcp-googlepay_button_type').val();
        buttonConfig.button.style.color = jQuery('#ppcp-googlepay_button_color').val();
        buttonConfig.button.style.language = jQuery('#ppcp-googlepay_button_language').val();
    }

    const createButton = function (ppcpConfig) {
        const selector = ppcpConfig.button.wrapper + 'GooglePay';

        if (!shouldDisplayPreviewButton()) {
            jQuery(selector).remove();
            return;
        }

        buttonConfig = JSON.parse(JSON.stringify(buttonConfig));
        buttonConfig.button.wrapper = selector;
        applyConfigOptions(buttonConfig);

        const wrapperElement = `<div id="${selector.replace('#', '')}" class="ppcp-button-apm ppcp-button-googlepay"></div>`;

        if (!jQuery(selector).length) {
            jQuery(ppcpConfig.button.wrapper).after(wrapperElement);
        } else {
            jQuery(selector).replaceWith(wrapperElement);
        }

        const button = new GooglepayButton(
            'preview',
            null,
            buttonConfig,
            ppcpConfig,
        );

        button.init(googlePayConfig);

        activeButtons[selector] = ppcpConfig;
    }

    const bootstrap = async function () {
        if (!widgetBuilder.paypal) {
            return;
        }

        googlePayConfig = await widgetBuilder.paypal.Googlepay().config();

        // We need to set bootstrapped here otherwise googlePayConfig may not be set.
        bootstrapped = true;

        let options;
        while (options = buttonQueue.pop()) {
            createButton(options.ppcpConfig);
        }
    };

    document.addEventListener(
        'DOMContentLoaded',
        () => {

            if (typeof (buttonConfig) === 'undefined') {
                console.error('PayPal button could not be configured.');
                return;
            }

            let paypalLoaded = false;
            let googlePayLoaded = false;

            const tryToBoot = () => {
                if (!bootstrapped && paypalLoaded && googlePayLoaded) {
                    bootstrap();
                }
            }

            // Load GooglePay SDK
            loadCustomScript({ url: buttonConfig.sdk_url }).then(() => {
                googlePayLoaded = true;
                tryToBoot();
            });

            // Wait for PayPal to be loaded externally
            if (typeof widgetBuilder.paypal !== 'undefined') {
                paypalLoaded = true;
                tryToBoot();
            }

            jQuery(document).on('ppcp-paypal-loaded', () => {
                paypalLoaded = true;
                tryToBoot();
            });
        },
    );

})({
    buttonConfig: window.wc_ppcp_googlepay_admin,
    jQuery: window.jQuery
});

// */
