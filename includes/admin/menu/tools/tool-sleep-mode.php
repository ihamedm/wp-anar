<div class="anar-tools-wrapper"
        <?php
        if(ANAR_SLEEP_MODE){
            echo "style=\"background: #ffeec4;
    border: 2px solid #ff9400;\"";
        }
        ?>
>
    <h2 class="awca_plugin_titles">Sleep Mode</h2>
    <p class="awca_plugin_subTitles">با فعالسازی این مُد کلیه محصولات انار <strong>ناموجود</strong> می شوند و قابل فروش نخواهند بود.</p>

    <div style="display: flex; align-items: center; justify-content: center">
        <?php
        if(ANAR_SLEEP_MODE)
            echo "<p style='color:red'>مُد خواب فعال است. محصولات انار غیر قابل فروش هستند!</p>";
        else
            echo "<p style='color:green'>مُد خواب غیرفعال است!</p>";
        ?>
    </div>


    <form method="post" id="anar-sleep-mode-form" class="anar-tool-ajax-form"
          data-reload="on"
          style="display: flex; align-items: center; justify-content: center">
        <input type="hidden" name="action" value="anar_toggle_sleep_mode">
        <?php wp_nonce_field('sleep_mode_ajax_nonce', 'sleep_mode_ajax_field'); ?>

        <div class="awca-switch-wrapper" style="margin-top: 30px">
            <label class="awca-switch">
                <input type="checkbox" name="anar-sleep-mode-toggle" id="anar-sleep-mode-toggle"
                        <?php checked(ANAR_SLEEP_MODE, true); ?> >
                <span class="awca-switch-slider"></span>
            </label>
            <span class="awca-switch-label">
                <?php echo ANAR_SLEEP_MODE ? 'فعال' : 'غیرفعال'; ?>
            </span>
        </div>

    </form>

</div>


<div class="modal micromodal-slide" id="anar-sleep-mode-modal" aria-hidden="true">
    <div class="modal__overlay" tabindex="-1" >
        <div class="modal__container " role="dialog" aria-modal="true" >
            <header class="modal__header">
                <h2 class="modal__title" id="anar-log-preview-modal-title">مُد خواب</h2>
            </header>
            <main class="modal__content">
                <p>این ویژگی برای <strong>غیرفعال سازی موقت</strong> فروش محصولات انار ایجاد شده است.</p>
                <strong>نکات مهم:</strong>
                <ol>
                    <li>بعد از فعال سازی مُد خواب امکان افزودن هیچ محصولی به سبد خرید برای کاربر وجود نخواهد داشت</li>
                    <li>بعد از فعالسازی مُد خواب، کل محصولات به تدریج در پس زمینه ناموجود خواهند شد.</li>
                    <li>در صورتی که مُد خواب را غیر فعال کنید کل محصولات به تدریج در پس زمینه موجود خواهند شد. با توجه به تعداد محصولات شما ممکن است این فرآیند ساعاتی طول بکشد.</li>
                </ol>

            </main>

            <footer class="modal__footer" style="text-align: left">
                <button type="button" class="button button-primary" data-micromodal-close>متوجه شدم</button>
            </footer>
        </div>

    </div>
</div>