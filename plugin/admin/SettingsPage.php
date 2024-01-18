<?php
namespace proto\plugin\admin;

use proto\App;
use proto\plugin\CheckConfig;

if (get_option('_' . CheckConfig::$clientId . '_application_created') === 'yes') {
    renderSettings();
    renderActiveShippingMethods();
    renderProductsWithMissingParameters();
} else {
    renderActivationErrorPage();
}

/**
 * Function to render Settings block on Settings Page
 * @return void
 */
function renderSettings(): void
{
    ?>
  <div class="container-fluid">

    <div class="h5 text-start fw-bold border-bottom pt-3 pb-3">
        <?= CheckConfig::$appName . ' ' . __('Settings', App::PLUGIN_TEXT_DOMAIN); ?>
    </div>

    <!--  Validate Credentials -->
    <div class="row border-bottom pt-3 pb-3 <?= CheckConfig::$clientId;?>-login_settings">

      <div class="col-4">
        <div class="h6 text-start pt-3 pb-1">
            <?= __('Login Settings', App::PLUGIN_TEXT_DOMAIN); ?>
        </div>
        <p class="q-my-md text-body2 fw-normal">
            <?= __('Login to your', App::PLUGIN_TEXT_DOMAIN). ' '
                . CheckConfig::$appName . ' '. __('account', App::PLUGIN_TEXT_DOMAIN) ;?>
        </p>
          <?php if (get_option('_' . CheckConfig::$clientId . '_authorized') == 'no') :?>
            <div class="notice notice-error  ms-0">
              <p class="q-my-md text-body2 fw-normal">
                  <?= __('Error: Invalid Authorization Code', App::PLUGIN_TEXT_DOMAIN);?>
              </p>
            </div>
          <?php endif;?>
          <?php if (get_option('_' . CheckConfig::$clientId . '_authorized') == 'yes') :?>
            <div class="notice notice-success  ms-0">
              <p class="q-my-md text-body2 fw-normal">
                  <?= __('Success: Your account has been successfully verified', App::PLUGIN_TEXT_DOMAIN);?>
              </p>
            </div>
          <?php endif;?>
      </div>

      <div class="col-8">
        <div class="row">
          <div class="col-8">
            <form method="POST" action="<?= esc_url(admin_url('admin.php')); ?>">
                <?php
                foreach (CheckConfig::$credentialsObject as $object) :
                    ?>
                  <div class="pt-3 pb-3">
                    <label for="<?= $object['key'];?>" class="form-label" style="font-size:0.8rem;">
                        <?=__($object['label'], App::PLUGIN_TEXT_DOMAIN);?>
                    </label>
                      <?php
                        if ($object['key'] === 'method_title') : ?>
                        <input type="<?= $object['type']; ?>"
                               name="<?= $object['key']; ?>"
                               class="form-control"
                               id="<?= $object['key']; ?>"
                               value="<?= CheckConfig::$methodTitle;?>"
                        >
                        <?php else : ?>
                        <input type="<?= $object['type']; ?>"
                               name="<?= $object['key']; ?>"
                               class="form-control"
                               id="<?= $object['key']; ?>"
                               value="<?= get_option('_' . CheckConfig::$clientId . '_authorization_code');?>"
                        >
                        <?php endif;?>
                  </div>
                <?php endforeach;?>


              <input type="hidden" name="action" value="pluginValidateCredentials" />
                <?php
                wp_nonce_field('pluginValidateCredentials', 'vc_message');
                submit_button();
                ?>
            </form>
          </div>
        </div>
        <div class="col-4"></div>
      </div>
    </div>
<?php } ?>

<?php
/**
 * Function to render Shipping Methods block on Settings Page
 * @return void
 */
function renderActiveShippingMethods()
{
    ?>
    <div class="row border-bottom pt-3 pb-3">
      <div class="col-4">
        <div class="h6 text-start pt-3 pb-1">
            <?= __('Shipping Methods', App::PLUGIN_TEXT_DOMAIN); ?>
        </div>
        <p class="q-my-md text-body2 fw-normal">
            <?= __('Currently active WooCommerce Shipping methods', App::PLUGIN_TEXT_DOMAIN); ?>
        </p>
      </div>
      <div class="col-8">
          <?php
            $listHtml = '<div class=" pt-3 pb-3 flex" >';
            $shipping_methods = WC()->shipping()->get_shipping_methods();
            foreach ($shipping_methods as $id => $shipping_method) {
                if (strstr(strtolower($shipping_method->method_title), strtolower(CheckConfig::$appName))) {
                    $listHtml .= '<p class="h6 fw-normal text-primary"  style="font-size:0.9rem;"> ' .
                               $shipping_method->method_title .
                               '</p>';
                } else {
                    $listHtml .= '<p class="h6 fw-normal"  style="font-size:0.9rem;"> ' .
                               $shipping_method->method_title .
                               '</p>';
                }
            }
            $listHtml .= '</div>';
            echo $listHtml;
            ?>

      </div>
    </div>
    <?php
}
?>

<?php
 /**
 * Function to render Products with Missing Parameters block on Settings Page
 * @return void
 */
function renderProductsWithMissingParameters()
{
    ?>
    <!-- Products with missing parameters -->
    <?php
        $productsToOutput =[];
    if (get_option('_' . CheckConfig::$clientId . '_authorized') === 'yes') {
        $productsToOutput = missingParametersProducts();
    }
    if (! empty($productsToOutput)) {
        $message = __(
            'Some of your Products have no weight or dimensions or shipping class 
        (see list below ). This may make shipping calculations for these items inaccurate or impossible.',
            App::PLUGIN_TEXT_DOMAIN
        );
        ?>
        <div class="row border-bottom pt-3 pb-3">

          <div class="col-4">
            <div class="h6 text-start pt-3 pb-1">
            <?= __('Products with missing parameters', App::PLUGIN_TEXT_DOMAIN); ?>
            </div>
            <p class="q-my-md text-body2 fw-normal text-danger"><?= $message; ?></p>
          </div>

          <div class="col-8">
            <div  style="height: 375px;
            overflow: hidden;
            overflow-y: scroll;
            padding: 0 !important;
            border: 1px solid #C3C4C7;
            width:90%;"
            >
              <table class="table table-sm table-striped pt-3 pb-3">
                <tr class="h6 text-start pt-3 pb-3"  style="font-size:16px;">
                  <th scope="col" class="h6 pt-3 pb-3 ps-3" style="font-size:16px;">
                  <?= __('Product ID', App::PLUGIN_TEXT_DOMAIN); ?></th>
                  <th scope="col" class="h6 pt-3 pb-3"  style="font-size:16px;">
                  <?= __('Product Name', App::PLUGIN_TEXT_DOMAIN); ?></th>
                  <th scope="col" class="h6 pt-3 pb-3"  style="font-size:16px;">
                  <?= __('Product Dimensions', App::PLUGIN_TEXT_DOMAIN); ?></th>
                  <th scope="col" class="h6 pt-3 pb-3"  style="font-size:16px;">
                  <?= __('Product Weight', App::PLUGIN_TEXT_DOMAIN); ?></th>
                  <th scope="col" class="h6 pt-3 pb-3"  style="font-size:16px;">
                  <?= __('Product Link', App::PLUGIN_TEXT_DOMAIN); ?></th>
                </tr>
                <tbody>
            <?php foreach ($productsToOutput as $product) : ?>
                  <tr  style="font-size:16px;">
                    <td class="ps-5 text-align-center"  style="font-size:16px;">
                        <?= $product['id'] ?></td>
                    <td  style="font-size:16px;"><?= $product['name'] ?></td>
                    <td  style="font-size:16px;"><?= $product['dimensions'] ?></td>
                    <td  style="font-size:16px;"><?= $product['weight'] ?></td>
                    <td  style="font-size:16px;">
                      <a href="<?= $product['link'] ?>" style="text-decoration-line: none">
                          <?= __('Edit Product', App::PLUGIN_TEXT_DOMAIN); ?>
                      </a>
                    </td>
                  </tr>
            <?php endforeach; ?>
                </tbody>
              </table>
            </div>

          </div>
        </div>
    <?php } ?>
<?php } ?>
<!--  End of container -->
</div>


<?php
/**
 * Return array of Woocommerce Products without the dimensions or weight
 * @return array
 */
function missingParametersProducts(): array
{
    $products  = wc_get_products(['numberposts' => -1]);

    $unfilledProducts = array_filter($products, function ($product) {
        if (! $product->get_weight() || $product->get_dimensions(false) === 'N/A' || ! $product->get_weight()) {
            return $product ;
        }
    });

    if (empty($unfilledProducts)) {
        return [];
    }

    return array_map(function ($product) {
        return [
            'id'          => $product->get_ID(),
            'name'        => $product->get_name(),
            'dimensions'  => $product->get_dimensions(),
            'shipping'    => $product->get_shipping_class() ? $product->get_shipping_class() : __('n/a', App::PLUGIN_TEXT_DOMAIN),
            'weight'      => $product->get_weight() ? $product->get_weight() : __('n/a', App::PLUGIN_TEXT_DOMAIN),
            'description' => substr($product->get_description(), 0, 50) . '...',
            'link'        => get_bloginfo('url') . '/wp-admin/post.php?post=' . $product->get_ID() . '&action=edit',
        ];
    }, $unfilledProducts);
}


/**
 * Function to render Error Page when SafeDigit Pipeline Application was not created
 * @return void
 */
function renderActivationErrorPage()
{
    echo  '<div class="container notice notice-error notice-alt">
        <div class="text-center mt-1" >
            <h5>'. __('Thanks for installing ' . App::PLUGIN_NAME .' plugin!', App::PLUGIN_TEXT_DOMAIN) . '</h5>
            <p>'. __('Unfortunatelly we have an Error during creating SafeDigit Pipeline Application', App::PLUGIN_TEXT_DOMAIN) . '</p>
            <p>'. __('To check and solve the problem please email', App::PLUGIN_TEXT_DOMAIN) . ' <a href="mailto:info@safedigit.io">info@safedigit.io</a></p>
        </div>
    </div>';
}
