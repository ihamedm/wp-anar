<?php
$api_data_handler = new \Anar\ApiDataHandler('attributes');
$awca_attributes_responses = $api_data_handler->getStoredApiResponse();
?>

<div class="stepContent" id="stepContent4">
  <h1 class='awca_plugin_titles'>معادل سازی ویژگی‌ها</h1>




    <?php if(isset($awca_attributes_responses['response'])):
        $awca_attributes = $awca_attributes_responses['response'];
    ?>


      <p class="awca_plugin_subTitles">
        در این مرحله می‌توانید ویژگی‌های محصولات انار را با ویژگی‌های موجود در وبسایت خودتان معادل سازی کنید در صورتی که معادل سازی انجام نشود، محصولات انار با همان ویژگی‌های خودشان در وبسایت شما ساخته می‌شوند
      </p>
      <div class="">
    <!-- <div class="category_title p-0">
        <h3>ویژگی انار</h3>
        <h3>ویژگی شما</h3>
      </div> -->
    <form action="<?php echo admin_url('admin-ajax.php'); ?>" method="post" id="plugin_attribute_creation_form">
      <div class="property_select_container">
        <input type="hidden" name="action" value="awca_handle_pair_attributes_ajax" />
        <?php wp_nonce_field('awca_handle_pair_attributes_ajax_nonce', 'awca_handle_pair_attributes_ajax_field'); ?>
        <?php
          $attributeMap = get_option( 'attributeMap' );
          $woocommerce_attributes = wc_get_attribute_taxonomies();
//
//        print_anar('$attributeMap', $attributeMap);
//        print_anar('$woocommerce_attributes', $woocommerce_attributes);
          ?>
        <?php if ($awca_attributes) : ?>
          <?php $count = 0; ?>
          <?php $key = 0; ?>
<!--          --><?php //foreach ($awca_attributes->items as $index => $item) : ?>
            <?php foreach ($awca_attributes as $attr_index => $anar_attribute) : ?>


              <div class="varient_box">
                  <span class="category-selects-index"><?php echo $attr_index+1;?></span>
                <?php
                $attribute_top_values = awca_limit_chars(implode('-', array_slice($anar_attribute->values, 0, 5)), 40);
                $attribute_verbose_name = Anar\Wizard\Attributes::get_final_attribute_name_from_attribute_and_category($anar_attribute->name, $attribute_top_values);
                $name = $anar_attribute->name;
                $slug = $anar_attribute->key;

                ?>
                    <input type="hidden" name="anar-atts[<?php echo $anar_attribute->key; ?>][name]" value="<?php echo $anar_attribute->name; ?>" />
                    <input type="hidden" name="anar-atts[<?php echo $anar_attribute->key; ?>][map]" value="select" />
                    <input type="hidden" name="anar-atts[<?php echo $anar_attribute->key; ?>][key]" value="<?php echo $anar_attribute->key; ?>" />
                  <label>
                      <input type="text" name="anar-atts[<?php echo $anar_attribute->key; ?>]" disabled value="<?php echo $anar_attribute->name; ?>" />
                      <?php printf('<span>مقادیر: %s ...</span>', $attribute_top_values); ?>
                  </label>
                  <?php

                if (!empty($woocommerce_attributes)) { ?>
                  <div class="select_wrapper">
                    <select name="product_attributes[<?php echo $anar_attribute->key; ?>][map]" class="varient_selcet">
                      <option value="select">انتخاب کنید</option>
                      <?php foreach ($woocommerce_attributes as $wc_attribute) : ?>
                        <?php
                            $wc_name = Anar\Wizard\Attributes::get_final_attribute_name_from_attribute_and_category($wc_attribute->attribute_label, $attribute_top_values);
                        // if we have existence an attribute before, the slug is saved in $attributeMap, we return selected on this check
                          $is_this_the_default_value = false;
                          if (isset($attributeMap[$anar_attribute->key])) {
                              if($attributeMap[$anar_attribute->key]['key'] == $wc_attribute->attribute_name || $attributeMap[$anar_attribute->key]['map'] == $wc_attribute->attribute_name){
                                  $is_this_the_default_value = true;
                              }
                          }

                        ?>
                        <option value="<?php echo $wc_attribute->attribute_name; ?>" <?php echo $is_this_the_default_value ? "selected='true'" : ''?> >
                            <?php
                            echo $wc_attribute->attribute_label;
                            ?>
                        </option>
                      <?php endforeach; ?>
                    </select>

                      <input type="hidden" name="product_attributes[<?php echo $anar_attribute->key; ?>][name]" value="<?php echo $anar_attribute->name; ?>">
                      <input type="hidden" name="product_attributes[<?php echo $anar_attribute->key; ?>][key]" value="<?php echo $anar_attribute->key; ?>">

                    <div class="select_icons_warpper">
                      <svg onclick="clearSelect(<?php echo $count; ?>)" class="clear-icon" style="cursor: pointer; display:none;" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M13.5306 7.53063L11.0603 10L13.5306 12.4694C13.6003 12.5391 13.6556 12.6218 13.6933 12.7128C13.731 12.8039 13.7504 12.9015 13.7504 13C13.7504 13.0985 13.731 13.1961 13.6933 13.2872C13.6556 13.3782 13.6003 13.4609 13.5306 13.5306C13.4609 13.6003 13.3782 13.6556 13.2872 13.6933C13.1961 13.731 13.0986 13.7504 13 13.7504C12.9015 13.7504 12.8039 13.731 12.7128 13.6933C12.6218 13.6556 12.5391 13.6003 12.4694 13.5306L10 11.0603L7.53063 13.5306C7.46095 13.6003 7.37822 13.6556 7.28718 13.6933C7.19613 13.731 7.09855 13.7504 7 13.7504C6.90146 13.7504 6.80388 13.731 6.71283 13.6933C6.62179 13.6556 6.53906 13.6003 6.46938 13.5306C6.3997 13.4609 6.34442 13.3782 6.30671 13.2872C6.269 13.1961 6.24959 13.0985 6.24959 13C6.24959 12.9015 6.269 12.8039 6.30671 12.7128C6.34442 12.6218 6.3997 12.5391 6.46938 12.4694L8.93969 10L6.46938 7.53063C6.32865 7.38989 6.24959 7.19902 6.24959 7C6.24959 6.80098 6.32865 6.61011 6.46938 6.46937C6.61011 6.32864 6.80098 6.24958 7 6.24958C7.19903 6.24958 7.3899 6.32864 7.53063 6.46937L10 8.93969L12.4694 6.46937C12.5391 6.39969 12.6218 6.34442 12.7128 6.3067C12.8039 6.26899 12.9015 6.24958 13 6.24958C13.0986 6.24958 13.1961 6.26899 13.2872 6.3067C13.3782 6.34442 13.4609 6.39969 13.5306 6.46937C13.6003 6.53906 13.6556 6.62178 13.6933 6.71283C13.731 6.80387 13.7504 6.90145 13.7504 7C13.7504 7.09855 13.731 7.19613 13.6933 7.28717C13.6556 7.37822 13.6003 7.46094 13.5306 7.53063ZM19.75 10C19.75 11.9284 19.1782 13.8134 18.1068 15.4168C17.0355 17.0202 15.5127 18.2699 13.7312 19.0078C11.9496 19.7458 9.98919 19.9389 8.09787 19.5627C6.20656 19.1865 4.46928 18.2579 3.10571 16.8943C1.74215 15.5307 0.813554 13.7934 0.437348 11.9021C0.061142 10.0108 0.254225 8.05042 0.992179 6.26884C1.73013 4.48726 2.97982 2.96451 4.58319 1.89317C6.18657 0.821828 8.07164 0.25 10 0.25C12.585 0.25273 15.0634 1.28084 16.8913 3.10872C18.7192 4.93661 19.7473 7.41498 19.75 10ZM18.25 10C18.25 8.3683 17.7661 6.77325 16.8596 5.41655C15.9531 4.05984 14.6646 3.00242 13.1571 2.37799C11.6497 1.75357 9.99085 1.59019 8.39051 1.90852C6.79017 2.22685 5.32016 3.01259 4.16637 4.16637C3.01259 5.32015 2.22685 6.79016 1.90853 8.3905C1.5902 9.99085 1.75358 11.6496 2.378 13.1571C3.00242 14.6646 4.05984 15.9531 5.41655 16.8596C6.77326 17.7661 8.36831 18.25 10 18.25C12.1873 18.2475 14.2843 17.3775 15.8309 15.8309C17.3775 14.2843 18.2475 12.1873 18.25 10Z" fill="#343330" />
                      </svg>

                      <svg width="20" height="20" style="cursor: pointer;" viewBox="0 0 18 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.0307 1.53055L9.53068 9.03055C9.46102 9.10029 9.3783 9.15561 9.28726 9.19335C9.19621 9.23109 9.09861 9.25052 9.00005 9.25052C8.90149 9.25052 8.80389 9.23109 8.71285 9.19335C8.6218 9.15561 8.53908 9.10029 8.46943 9.03055L0.969426 1.53055C0.828695 1.38982 0.749634 1.19895 0.749634 0.999929C0.749634 0.800906 0.828695 0.610034 0.969426 0.469303C1.11016 0.328573 1.30103 0.249512 1.50005 0.249512C1.69907 0.249512 1.88995 0.328573 2.03068 0.469303L9.00005 7.43962L15.9694 0.469303C16.0391 0.399621 16.1218 0.344345 16.2129 0.306633C16.3039 0.268921 16.4015 0.249512 16.5001 0.249512C16.5986 0.249512 16.6962 0.268921 16.7872 0.306633C16.8783 0.344345 16.961 0.399621 17.0307 0.469303C17.1004 0.538986 17.1556 0.621712 17.1933 0.712756C17.2311 0.803801 17.2505 0.901383 17.2505 0.999929C17.2505 1.09847 17.2311 1.19606 17.1933 1.2871C17.1556 1.37815 17.1004 1.46087 17.0307 1.53055Z" fill="#343330" />
                      </svg>
                    </div>
                  </div>
                <?php } ?>
              </div>
            <?php endforeach; ?>
            <?php $key = $key + 1; ?>
<!--          --><?php //endforeach; ?>
      </div>

      <div class="stepper_button_container">
        <a data-next-step="2" class="plugin_activation_button prev_stepper_btn" name="category_create_submit">
          مرحله قبل
        </a>
        <button type="submit" class="plugin_activation_button stepper_btn configuration_save_button" name="category_create_submit">
          مرحله بعد
          <svg class="spinner-loading" width="24px" height="24px" viewBox="0 0 66 66" xmlns="http://www.w3.org/2000/svg">
            <circle class="path" fill="none" stroke-width="6" stroke-linecap="round" cx="33" cy="33" r="30"></circle>
          </svg>
        </button>

      </div>




    <?php else : ?>
      بازیابی اطلاعات ویژگی ها با مشکل مواجه شد لطفا صفحه را بروزرسانی نمایید
    <?php endif; ?>
    </form>


  </div>

    <?php endif;?>



</div>