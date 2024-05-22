import {useEffect} from '@wordpress/element';
import {usePayPalCardFields} from "@paypal/react-paypal-js";

export const CheckoutHandler = ({getCardFieldsForm, getSavePayment, saveCardText, is_vaulting_enabled}) => {
    const {cardFieldsForm} = usePayPalCardFields();

    useEffect(() => {
        getCardFieldsForm(cardFieldsForm)
    }, []);

    if (!is_vaulting_enabled) {
        return null;
    }

    return (
        <>
            <input type="checkbox" id="save" name="save" onChange={(e) => getSavePayment(e.target.checked)}/>
            <label htmlFor="save">{saveCardText}</label>
        </>
    )
}
