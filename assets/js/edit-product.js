(function($) {
    'use strict';

    class AnarPriceSync {
        constructor() {
            this.priceFields = $('._regular_price_field, ._sale_price_field');
            this.isVariable = anarPriceData.isVariable;
            this.init();
        }

        init() {
            if (this.isVariable) {
                this.initVariableProduct();
            } else {
                this.initSimpleProduct();
            }
        }

        initVariableProduct() {
            // Handle variable product price fields in the variations panel
            this.setupVariationPriceFields();
            this.bindVariationEvents();
        }

        setupVariationPriceFields() {
            const self = this;
            
            // Handle existing variations
            $('.woocommerce_variation').each(function() {
                self.setupSingleVariation($(this));
            });

            // Handle new variations being added
            $('#variable_product_options').on('woocommerce_variations_added', function() {
                $('.woocommerce_variation').each(function() {
                    if (!$(this).data('anar-price-sync-initialized')) {
                        self.setupSingleVariation($(this));
                    }
                });
            });
        }

        setupSingleVariation($variation) {
            if ($variation.data('anar-price-sync-initialized')) {
                return;
            }

            const variationId = $variation.find('.variation_id').val();
            const variationData = anarPriceData.variations[variationId];
            
            if (!variationData) {
                return;
            }

            const priceFields = $variation.find('.variable_regular_price, .variable_sale_price');
            
            // Add notice and checkbox before price fields
            const noticeHtml = this.getVariationNoticeHtml(variationData);
            priceFields.first().before(noticeHtml);

            // Setup price validation
            this.setupPriceValidation(priceFields, variationData);

            // Mark as initialized
            $variation.data('anar-price-sync-initialized', true);
        }

        getVariationNoticeHtml(variationData) {
            const isDisabled = variationData.syncStatus === 'disabled';
            return `
                <div class="anar-price-notice ${isDisabled ? 'disabled' : 'enabled'}">
                    ${this.getNoticeText(variationData)}
                </div>
                <p class="form-field">
                    <label>کنترل قیمت توسط شما</label>
                    <input type="checkbox" class="checkbox anar_variation_price_sync_disable" 
                           ${isDisabled ? 'checked' : ''}>
                    <span class="description">غیرفعال کردن همگام‌سازی قیمت برای این تنوع</span>
                </p>
            `;
        }

        setupPriceValidation(priceFields, variationData) {
            const self = this;
            priceFields.each(function() {
                $(this).after('<div class="price-error-message">قیمت وارد شده خارج از محدوده مجاز است</div>');
                
                $(this).on('input', function() {
                    if (variationData.syncStatus === 'disabled') {
                        self.validatePrice($(this), variationData.minPrice, variationData.maxPrice);
                    }
                });
            });
        }

        getNoticeText() {
            if (anarPriceData.syncStatus === 'disabled') {
                let text = 'قیمت این محصول توسط انار کنترل نمی شود. مسئولیت قیمت به عهده خودتان می باشد. اطلاعات بیشتر';
                if (anarPriceData.minPrice && anarPriceData.maxPrice) {
                    text += `<div class="anar-price-range">محدوده قیمت مجاز: ${Number(anarPriceData.minPrice).toLocaleString()} تا ${Number(anarPriceData.maxPrice).toLocaleString()} تومان</div>`;
                }
                return text;
            }
            return 'قیمت این محصول همیشه با قیمت محصول در پنل انار همگام سازی میشود.';
        }

        validatePrice(input) {
            const price = parseInt(input.val().replace(/,/g, ''));
            const errorMessage = input.closest('.form-field').find('.price-error-message');
            
            if (price && (price < anarPriceData.minPrice || price > anarPriceData.maxPrice)) {
                input.addClass('price-error');
                errorMessage.show();
                return false;
            } else {
                input.removeClass('price-error');
                errorMessage.hide();
                return true;
            }
        }

        bindVariationEvents() {
            const self = this;
            $(document).on('change', '.anar_variation_price_sync_disable', function() {
                const $variation = $(this).closest('.woocommerce_variation');
                const variationId = $variation.find('.variation_id').val();
                const isChecked = $(this).is(':checked');

                self.toggleVariationPriceSync(variationId, isChecked, $variation);
            });
        }

        toggleVariationPriceSync(variationId, isChecked, $variation) {
            const self = this;
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'toggle_anar_price_sync',
                    product_id: anarPriceData.productId,
                    variation_id: variationId,
                    status: isChecked ? 'disabled' : 'enabled',
                    nonce: anarPriceData.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.updateVariationUI(isChecked, $variation, anarPriceData.variations[variationId]);
                    }
                }
            });
        }

        updateVariationUI(isChecked, $variation, variationData) {
            const priceFields = $variation.find('.variable_regular_price, .variable_sale_price');
            const notice = $variation.find('.anar-price-notice');

            priceFields.prop('readonly', !isChecked)
                      .css({
                          'backgroundColor': isChecked ? '#ffffff' : '#f0f0f1',
                          'pointerEvents': isChecked ? 'auto' : 'none'
                      });

            notice.removeClass('enabled disabled')
                  .addClass(isChecked ? 'disabled' : 'enabled')
                  .html(this.getNoticeText(variationData));

            if (!isChecked) {
                priceFields.removeClass('price-error');
                $variation.find('.price-error-message').hide();
            } else {
                priceFields.each((i, field) => {
                    this.validatePrice($(field), variationData.minPrice, variationData.maxPrice);
                });
            }
        }
    }

    $(document).ready(function() {
        new AnarPriceSync();
    });

})(jQuery);