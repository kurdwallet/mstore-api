<?php
/*
* Template Name: Mstore API
*/

function getValue(&$val, $default = '')
{
    return isset($val) ? $val : $default;
}

$data = null;
if (isset($_POST['order'])) {
    $data = json_decode(urldecode(base64_decode(sanitize_text_field($_POST['order']))), true);
} elseif (filter_has_var(INPUT_GET, 'order')) {
    $data = filter_has_var(INPUT_GET, 'order') ? json_decode(urldecode(base64_decode(sanitize_text_field(filter_input(INPUT_GET, 'order')))), true) : [];
} elseif (filter_has_var(INPUT_GET, 'code')) {
    $code = sanitize_text_field(filter_input(INPUT_GET, 'code'));
    global $wpdb;
    $table_name = $wpdb->prefix . "mstore_checkout";
    $item = $wpdb->get_row("SELECT * FROM $table_name WHERE code = '$code'");
    if ($item) {
        $data = json_decode(urldecode(base64_decode($item->order)), true);
    } else {
        return var_dump("Can't not get the order");
    }
}

if ($data != null):
    global $woocommerce;
    // Validate the cookie token
    $userId = validateCookieLogin($data['token']);
    if (is_wp_error($userId)) {
        return var_dump($userId);
    }

    // Check user and authentication
    $user = get_userdata($userId);
    if ($user) {
        if (!is_user_logged_in()) {
            wp_set_current_user($userId, $user->user_login);
            wp_set_auth_cookie($userId);

            $url = filter_has_var(INPUT_SERVER, 'REQUEST_URI') ? filter_input(INPUT_SERVER, 'REQUEST_URI') : '';
            header("Refresh: 0; url=$url");
        }
    }
    $woocommerce->session->set('refresh_totals', true);
    $woocommerce->cart->empty_cart();

    // Get product info
    $billing = $data['billing'];
    $shipping = $data['shipping'];
    $products = $data['line_items'];
    foreach ($products as $product) {
        $productId = absint($product['product_id']);

        $quantity = $product['quantity'];
        $variationId = getValue($product['variation_id'], null);

        // Check the product variation
        if (!empty($variationId)) {
            $productVariable = new WC_Product_Variable($productId);
            $listVariations = $productVariable->get_available_variations();
            foreach ($listVariations as $vartiation => $value) {
                if ($variationId == $value['variation_id']) {
                    $attribute = $value['attributes'];
                    $woocommerce->cart->add_to_cart($productId, $quantity, $variationId, $attribute);
                }
            }
        } else {
            $woocommerce->cart->add_to_cart($productId, $quantity);
        }
    }


    if (!empty($data['coupon_lines'])) {
        $coupons = $data['coupon_lines'];
        foreach ($coupons as $coupon) {
            $woocommerce->cart->add_discount($coupon['code']);
        }
    }

    //$shippingMethod = '';
    if (!empty($data['shipping_lines'])) {
        $shipping_methods = [];
        foreach ($data['shipping_lines'] as $shipping_line)
        {
            $vendor_id = $shipping_line['vendor_id'];
            $method_id = $shipping_line['method_id'];
            $shipping_methods[$vendor_id] = $method_id;
        }
        WC()->session->set("chosen_shipping_methods", $shipping_methods);
        $shippingLines = $data['shipping_lines'];
        //$shippingMethod = $shippingLines[0]['method_id'];
    }
// KP
    $delivery_lat = $shipping["wcfmmp_user_location_lat"];
    $delivery_lng = $shipping["wcfmmp_user_location_lng"];
    $delivery_address = $shipping["wcfmmp_user_location"];

    WC()->customer->set_shipping_first_name($shipping["first_name"]);
    WC()->customer->set_shipping_last_name($shipping["last_name"]);
    WC()->customer->set_shipping_company($shipping["company"]);
    WC()->customer->set_shipping_address_1($shipping["address_1"]);
    WC()->customer->set_shipping_address_2($shipping["address_2"]);
    WC()->customer->set_shipping_city($shipping["city"]);
    WC()->customer->set_shipping_state($shipping["state"]);
    WC()->customer->set_shipping_postcode($shipping["postcode"]);
    WC()->customer->set_shipping_country($shipping["country"]);

    WC()->customer->set_props( array( 'wcfmmp_user_location' => $delivery_address ) );
    WC()->session->set("_wcfmmp_user_location", $delivery_address);
    WC()->session->set("_wcfmmp_user_location_lat", $delivery_lat);
    WC()->session->set("_wcfmmp_user_location_lng", $delivery_lng);

    // Check Terms Checkbox

    $shipping_methods1 = WC()->shipping->calculate_shipping(WC()->cart->get_shipping_packages());
    $method_counts = [];
    $previous_shippings = [];
    foreach ($shipping_methods1 as $key => $item)
    {
        $method_counts[$key] = count($item['rates']);
        if(isset($item['rates']))
        {
            $rates = [];
            foreach ($item['rates'] as $rate)
            {
                $rates[] = $rate->id;
            }
            $previous_shippings[$key] = $rates;
            unset($rates);
        }
    }
    //print_r($previous_shipping);
    WC()->session->set( 'previous_shipping_methods', $previous_shippings );
    WC()->session->set( 'shipping_method_counts', $method_counts );
   // print_r($method_counts);

    add_filter('woocommerce_terms_is_checked_default', function () { return true;}, 10000, 2);
    WC()->session->save_data();

    //print_r(WC()->session->get_session_data());
    //echo PHP_EOL;
    //print_r(WC()->session->chosen_shipping_methods[ 27 ]);
    //echo PHP_EOL;
    //print_r(WC()->shipping()->get_packages());
//    return '';
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?> >
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="profile" href="http://gmpg.org/xfn/11">
        <?php wp_head(); ?>
    </head>

    <body <?php body_class(); ?> >

    <div id="page" class="site">
        <div class="site-content-contain">
            <div id="content" class="site-content">
                <div class="wrap">
                    <div id="primary" class="content-area">
                        <main id="main" class="site-main" role="main">
                            <article id="post-6" class="post-6 page type-page status-publish hentry">
                                <div class="entry-content">
                                    <div class="woocommerce">
                                        <?php
                                        wc_print_notices();
                                        ?>
                                        <form
                                                name="checkout" method="post"
                                                class="checkout woocommerce-checkout"
                                                action="<?php echo esc_url(get_bloginfo('url')); ?>/checkout/"
                                                enctype="multipart/form-data">
                                            <?php do_action('woocommerce_checkout_before_customer_details'); ?>
                                            <?php foreach ($shipping_methods as $i => $method) : ?>
                                                <input name="shipping_method[<?php echo $i; ?>]" data-index="<?php echo $i; ?>" value="<?php echo esc_html($method); ?>" type="hidden">
                                            <?php endforeach; ?>
                                            <input type="hidden"
                                                   name="billing_wcfmmp_user_location_lat" id="billing_wcfmmp_user_location_lat"
                                                   value="<?php echo $delivery_lat; ?>"/>
                                            <input type="hidden"
                                                   name="billing_wcfmmp_user_location_lng" id="billing_wcfmmp_user_location_lng"
                                                   value="<?php echo $delivery_lng; ?>"/>
                                            <input type="hidden"
                                                   name="billing_wcfmmp_user_location" id="wcfmmp_user_location"
                                                   value="<?php echo $delivery_address; ?>"/>

                                            <input type="hidden"
                                                   name="wcfmmp_user_location_lng" id="wcfmmp_user_location_lng"
                                                   value="<?php echo $delivery_lng; ?>"/>
                                            <input type="hidden"
                                                   name="wcfmmp_user_location_lat" id="wcfmmp_user_location_lat"
                                                   value="<?php echo $delivery_lat; ?>"/>
                                            <input type="hidden"
                                                   name="wcfmmp_user_location" id="wcfmmp_user_location"
                                                   value="<?php echo $delivery_address; ?>"/>
                                            <div class="col2-set" id="customer_details">
                                                <div class="col-1">
                                                    <div class="woocommerce-billing-fields">

                                                        <h3>Billing details</h3>

                                                        <div class="woocommerce-billing-fields__field-wrapper">
                                                            <p class="form-row form-row-first validate-required"
                                                               id="billing_first_name_field" data-priority="10">
                                                                <label for="billing_first_name" class="">First name
                                                                    <abbr class="required" title="required">*</abbr>
                                                                </label>
                                                                <input class="input-text "
                                                                       name="billing_first_name" id="billing_first_name"
                                                                       placeholder=""
                                                                       value="<?php echo isset($billing['first_name']) ? esc_html(getValue($billing['first_name'])) : ''; ?>"/>
                                                            </p>
                                                            <p class="form-row form-row-last validate-required"
                                                               id="billing_last_name_field" data-priority="20">
                                                                <label for="billing_last_name" class="">Last name <abbr
                                                                            class="required" title="required">*</abbr>
                                                                </label>
                                                                <input class="input-text "
                                                                       name="billing_last_name" id="billing_last_name"
                                                                       placeholder=""
                                                                       value="<?php echo isset($billing['last_name']) ? esc_html(getValue($billing['last_name'])) : ''; ?>"/>
                                                            </p>
                                                            <p class="form-row form-row-wide" id="billing_company_field"
                                                               data-priority="30">
                                                                <label for="billing_company" class="">Company
                                                                    name</label>
                                                                <input class="input-text "
                                                                       name="billing_company" id="billing_company"
                                                                       placeholder=""
                                                                       value="<?php echo isset($data['billing_company']) ? esc_html(getValue($data['billing_company'])) : ''; ?>"/>
                                                            </p>
                                                            <p class="form-row form-row-wide address-field update_totals_on_change validate-required"
                                                               id="billing_country_field" data-priority="40">
                                                                <label for="billing_country" class="">Country <abbr
                                                                            class="required" title="required">*</abbr>
                                                                </label>
                                                                <input class="input-text "
                                                                       name="billing_country" id="billing_country"
                                                                       placeholder=""
                                                                       value="<?php echo isset($billing['country']) ? esc_html(getValue($billing['country'])) : ''; ?>"/>

                                                            </p>
                                                            <p class="form-row form-row-wide address-field validate-required"
                                                               id="billing_address_1_field" data-priority="50">
                                                                <label for="billing_address_1" class="">Address <abbr
                                                                            class="required" title="required">*</abbr>
                                                                </label>
                                                                <input class="input-text "
                                                                       name="billing_address_1" id="billing_address_1"
                                                                       placeholder="Street address"
                                                                       value="<?php echo isset($billing['address_1']) ? esc_html(getValue($billing['address_1'])) : ''; ?>"/>
                                                            </p>
                                                            <p class="form-row form-row-wide address-field"
                                                               id="billing_address_2_field" data-priority="60">
                                                                <input class="input-text "
                                                                       name="billing_address_2" id="billing_address_2"
                                                                       placeholder="Apartment, suite, unit etc. (optional)"
                                                                       value="<?php echo isset($billing['address_2']) ? esc_html(getValue($billing['address_2'])) : ''; ?>"/>
                                                            </p>
                                                            <p class="form-row form-row-wide address-field validate-required"
                                                               id="billing_city_field" data-priority="70">
                                                                <label for="billing_city" class="">Town / City <abbr
                                                                            class="required" title="required">*</abbr>
                                                                </label>
                                                                <input class="input-text "
                                                                       name="billing_city" id="billing_city"
                                                                       placeholder=""
                                                                       value="<?php echo isset($billing['city']) ? esc_html(getValue($billing['city'])) : ''; ?>"/>
                                                            </p>
                                                            <p class="form-row form-row-wide address-field validate-state"
                                                               id="billing_state_field" style="display: none">
                                                                <label for="billing_state" class="">State /
                                                                    County</label>
                                                                <input class="hidden" name="billing_state"
                                                                       id="billing_state"
                                                                       value="<?php echo isset($billing['state']) ? esc_html(getValue($billing['state'])) : ''; ?>"/>
                                                            </p>
                                                            <p class="form-row form-row-wide address-field validate-postcode"
                                                               id="billing_postcode_field" data-priority="65">
                                                                <label for="billing_postcode" class="">Postcode /
                                                                    ZIP</label>
                                                                <input class="input-text "
                                                                       name="billing_postcode" id="billing_postcode"
                                                                       placeholder=""
                                                                       value="<?php echo isset($billing['postcode']) ? esc_html(getValue($billing['postcode'])) : ''; ?>"/>
                                                            </p>
                                                            <p class="form-row form-row-first validate-phone"
                                                               id="billing_phone_field" data-priority="100">
                                                                <label for="billing_phone" class="">Phone</label>
                                                                <input class="input-text "
                                                                       name="billing_phone" id="billing_phone"
                                                                       placeholder=""
                                                                       value="<?php echo isset($billing['phone']) ? esc_html(getValue($billing['phone'])) : ''; ?>"/>
                                                            </p>
                                                            <p class="form-row form-row-last validate-required validate-email"
                                                               id="billing_email_field" data-priority="110">
                                                                <label for="billing_email" class="">Email address <abbr
                                                                            class="required" title="required">*</abbr>
                                                                </label>
                                                                <input class="input-text "
                                                                       name="billing_email" id="billing_email"
                                                                       placeholder=""
                                                                       value="<?php echo isset($billing['email']) ? esc_html(getValue($billing['email'])) : ''; ?>"/>
                                                            </p>
                                                        </div>

                                                    </div>
                                                </div>

                                                <div class="col-2">
                                                    <div class="woocommerce-shipping-fields">
                                                        <h3 id="ship-to-different-address">
                                                            <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                                                                <input id="ship-to-different-address-checkbox"
                                                                       class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox"
                                                                       type="checkbox" name="ship_to_different_address"
                                                                       value="1"/>
                                                                <span>Ship to a different address?</span>
                                                            </label>
                                                        </h3>

                                                        <div class="shipping_address">
                                                            <div class="woocommerce-shipping-fields__field-wrapper">
                                                                <p class="form-row form-row-first validate-required"
                                                                   id="shipping_first_name_field" data-priority="10">
                                                                    <label for="shipping_first_name" class="">First name
                                                                        <abbr class="required" title="required">*</abbr>
                                                                    </label>
                                                                    <input class="input-text "
                                                                           name="shipping_first_name"
                                                                           id="shipping_first_name" placeholder=""
                                                                           value="<?php echo isset($shipping['first_name']) ? esc_html(getValue($shipping['first_name'])) : ''; ?>"/>
                                                                </p>
                                                                <p class="form-row form-row-last validate-required"
                                                                   id="shipping_last_name_field" data-priority="20">
                                                                    <label for="shipping_last_name" class="">Last name
                                                                        <abbr class="required" title="required">*</abbr>
                                                                    </label>
                                                                    <input class="input-text "
                                                                           name="shipping_last_name"
                                                                           id="shipping_last_name" placeholder=""
                                                                           value="<?php echo isset($shipping['last_name']) ? esc_html(getValue($shipping['last_name'])) : ''; ?>"/>
                                                                </p>
                                                                <p class="form-row form-row-wide"
                                                                   id="shipping_company_field" data-priority="30">
                                                                    <label for="shipping_company" class="">Company
                                                                        name</label>
                                                                    <input class="input-text "
                                                                           name="shipping_company" id="shipping_company"
                                                                           placeholder=""
                                                                           value="<?php echo isset($shipping['company']) ? esc_html(getValue($shipping['company'])) : ''; ?>"/>
                                                                </p>
                                                                <p class="form-row form-row-wide address-field update_totals_on_change validate-required"
                                                                   id="shipping_country_field" data-priority="40">
                                                                    <label for="shipping_country" class="">Country <abbr
                                                                                class="required"
                                                                                title="required">*</abbr>
                                                                    </label>
                                                                    <input class="input-text "
                                                                           name="shipping_country" id="shipping_country"
                                                                           placeholder=""
                                                                           value="<?php echo isset($shipping['country']) ? esc_html(getValue($shipping['country'])) : ''; ?>"/>
                                                                </p>
                                                                <p class="form-row form-row-wide address-field validate-required"
                                                                   id="shipping_address_1_field" data-priority="50">
                                                                    <label for="shipping_address_1" class="">Address
                                                                        <abbr class="required" title="required">*</abbr>
                                                                    </label>
                                                                    <input class="input-text "
                                                                           name="shipping_address_1"
                                                                           id="shipping_address_1"
                                                                           placeholder="Street address"
                                                                           value="<?php echo isset($shipping['address_1']) ? esc_html(getValue($shipping['address_1'])) : ''; ?>"/>
                                                                </p>
                                                                <p class="form-row form-row-wide address-field"
                                                                   id="shipping_address_2_field" data-priority="60">
                                                                    <input class="input-text "
                                                                           name="shipping_address_2"
                                                                           id="shipping_address_2"
                                                                           placeholder="Apartment, suite, unit etc. (optional)"
                                                                           value="<?php echo isset($shipping['address_2']) ? esc_html(getValue($shipping['address_2'])) : ''; ?>"/>
                                                                </p>
                                                                <p class="form-row form-row-wide address-field validate-required"
                                                                   id="shipping_city_field" data-priority="70">
                                                                    <label for="shipping_city" class="">Town / City
                                                                        <abbr class="required" title="required">*</abbr>
                                                                    </label>
                                                                    <input class="input-text "
                                                                           name="shipping_city" id="shipping_city"
                                                                           placeholder=""
                                                                           value="<?php echo isset($shipping['city']) ? esc_html(getValue($shipping['city'])) : ''; ?>"/>
                                                                </p>
                                                                <p class="form-row form-row-wide address-field validate-state"
                                                                   id="shipping_state_field" style="display: none">
                                                                    <label for="shipping_state" class="">State /
                                                                        County</label>
                                                                    <input class="hidden"
                                                                           name="shipping_state" id="shipping_state"
                                                                           value="<?php echo isset($shipping['state']) ? esc_html(getValue($shipping['state'])) : ''; ?>"
                                                                           placeholder=""/>
                                                                </p>
                                                                <p class="form-row form-row-wide address-field validate-postcode"
                                                                   id="shipping_postcode_field" data-priority="65">
                                                                    <label for="shipping_postcode" class="">Postcode /
                                                                        ZIP</label>
                                                                    <input class="input-text "
                                                                           name="shipping_postcode"
                                                                           id="shipping_postcode" placeholder=""
                                                                           value="<?php echo isset($shipping['postcode']) ? esc_html(getValue($shipping['postcode'])) : '';; ?>"/>
                                                                </p>
                                                            </div>
                                                        </div>

                                                    </div>
                                                    <?php do_action('woocommerce_before_order_notes'); ?>
                                                    <div class="woocommerce-additional-fields">
                                                        <div class="woocommerce-additional-fields__field-wrapper">
                                                            <p class="form-row notes" id="order_comments_field"
                                                               data-priority="">
                                                                <label for="order_comments" class="">Order notes</label>
                                                                <textarea name="order_comments" class="input-text "
                                                                          id="order_comments"
                                                                          placeholder="Notes about your order, e.g. special notes for delivery."
                                                                          rows="2" cols="5"
                                                                          value="<?php echo isset($data['customer_note']) ? esc_html($data['customer_note']) : ''; ?>"><?php echo isset($data['customer_note']) ? esc_html($data['customer_note']) : ''; ?></textarea>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <?php do_action('woocommerce_after_order_notes'); ?>
                                                </div>
                                            </div>

                                            <?php do_action('woocommerce_checkout_after_customer_details'); ?>

                                            <?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>

                                            <h3 id="order_review_heading"><?php esc_html_e( 'Your order', 'woocommerce' ); ?></h3>

                                            <?php do_action( 'woocommerce_checkout_before_order_review' ); ?>

                                            <div id="order_review" class="woocommerce-checkout-review-order">
                                                <?php do_action( 'woocommerce_checkout_order_review' ); ?>
                                            </div>

                                            <?php do_action( 'woocommerce_checkout_after_order_review' ); ?>

                                        </form>

                                    </div>
                                </div>
                                <!-- .entry-content -->
                            </article>
                            <!-- #post-## -->
                        </main>
                        <!-- #main -->
                    </div>
                    <!-- #primary -->
                </div>
                <!-- .wrap -->
            </div>
            <!-- #content -->
        </div>
        <!-- .site-content-contain -->
    </div>
    <!-- #page -->
    <?php wp_footer(); ?>
    <script type="text/javascript">
        setTimeout(function () {
            document.getElementById('place_order').click();
        }, 2000);
    </script>
    </body>
    </html>
<?php
endif;
?>
