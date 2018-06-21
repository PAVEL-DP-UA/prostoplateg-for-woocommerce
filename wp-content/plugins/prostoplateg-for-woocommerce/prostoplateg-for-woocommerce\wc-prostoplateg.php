<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
/**
 * Plugin Name: ProstoPlateg.kz — платежный шлюз
 * Description: ProstoPlateg.kz платежный шлюз.
 * Version: 1.0.0
 * Author: Money.ua
 */
 
/* Добавляем пользовательский класс оплаты в WC  */
add_action('plugins_loaded', 'woocommerce_prostoplateg', 0);

function transliterate($st) {    $st = strtr($st, array( 'ж'=>'zh','ё'=>'yo','ч'=>'ch','ш'=>'sh','щ'=>'shch','ю'=>'yu','я'=>'ya',      'Ж'=>'Zh','Ё'=>'Yo','Ч'=>'Ch','Ш'=>'Sh','Щ'=>'Shch','Ю'=>'Yu','Я'=>'Ya','ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss','Ä'=>'Ae','Ö'=>'Oe','Ü'=>'Ue',      'æ'=>'ae','Æ'=>'Ae','Á'=>'A','À'=>'A','Â'=>'A','Ą'=>'A','Å'=>'A','Ç'=>'C','Ć'=>'C','Č'=>'C','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E','Ę'=>'E','Ė'=>'E','Ě'=>'E',      'Ğ'=>'G','Î'=>'I','Ï'=>'I','İ'=>'I','Í'=>'I','I'=>'I','Į'=>'I','Ł'=>'L','Ĺ'=>'L','Ñ'=>'N','Ń'=>'N','Ó'=>'O','Ô'=>'O','Ø'=>'O','Ŕ'=>'R','Ř'=>'R','Ś'=>'S','Š'=>'S','Ş'=>'S',      'Ú'=>'U','Ù'=>'U','Û'=>'U','Ų'=>'U','Ū'=>'U','Ů'=>'U','Ý'=>'Y','Ź'=>'Z','Ż'=>'Z','Ž'=>'Z','á'=>'a','à'=>'a','â'=>'a','ą'=>'a','å'=>'a','ç'=>'c','ć'=>'c','č'=>'c','é'=>'e',      'è'=>'e','ê'=>'e','ë'=>'e','ę'=>'e','ė'=>'e','ě'=>'e','ğ'=>'g','î'=>'i','ï'=>'i','i'=>'i','í'=>'i','ı'=>'i','į'=>'i','ł'=>'l','ĺ'=>'l','ñ'=>'n','ń'=>'n','ó'=>'o','ô'=>'o',      'ø'=>'o','ŕ'=>'r','ř'=>'r','ś'=>'s','š'=>'s','ş'=>'s','ú'=>'u','ù'=>'u','û'=>'u','ų'=>'u','ū'=>'u','ů'=>'u','ý'=>'y','ź'=>'z','ż'=>'z','ž'=>'z','А'=>'A','Б'=>'B','В'=>'V',      'Г'=>'G','Ґ'=>'G','Д'=>'D','Е'=>'E','Є'=>'E','З'=>'Z',      'И'=>'I','Й'=>'Y','І'=>'I','Ї'=>'I','К'=>'K','Л'=>'L','М'=>'M','Н'=>'N','О'=>'O','П'=>'P','Р'=>'R','С'=>'S','Т'=>'T','У'=>'U','Ф'=>'F','Х'=>'H','Ц'=>'C','Ы'=>'Y','Э'=>'E',      'а'=>'a','б'=>'b','в'=>'v','г'=>'g','ґ'=>'g','д'=>'d','е'=>'e','є'=>'e','з'=>'z','и'=>'i','й'=>'y','і'=>'i','ї'=>'i','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p',      'р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ы'=>'y','э'=>'e','Ъ'=>'','Ь'=>'','ъ'=>'','ь'=>'',      ));   return $st; }

function woocommerce_prostoplateg()
{
    if (!class_exists('WC_Payment_Gateway'))
        return; // ничего не делать если класс шлюза WC-оплаты недоступен
    if (class_exists('WC_Prostoplateg'))
        return;
    class WC_Prostoplateg extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $plugin_dir = plugin_dir_url(__FILE__);

            global $woocommerce;

            $this->id = 'prostoplateg';
            $this->icon = apply_filters('woocommerce_prostoplateg_icon', '' . $plugin_dir . 'prostoplateg.png');
            $this->has_fields = false;

            // Load the settings
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->MNT_URL = $this->get_option('MNT_URL');
            $this->MNT_ID = $this->get_option('MNT_ID');
            $this->MNT_DATAINTEGRITY_CODE = $this->get_option('MNT_DATAINTEGRITY_CODE');
            $this->MNT_TEST_MODE = $this->get_option('MNT_TEST_MODE');
            $this->autosubmitpawform = $this->get_option('autosubmitpawform');
            $this->iniframe = $this->get_option('iniframe');
            $this->debug = $this->get_option('debug');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option('instructions');

            // Logs
            if ($this->debug == 'yes') {
                $this->log = new WC_Logger();
            }

            // Actions
            add_action('woocommerce_receipt_prostoplateg', array($this, 'receipt_page'));

            // Save options
            add_action('woocommerce_update_options_payment_gateways_prostoplateg', array($this, 'process_admin_options'));

            // Payment listener/API hook
            add_action('woocommerce_api_wc_prostoplateg', array($this, 'check_return_response'));

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }

        /**
         * Проверяем, включены ли поддержимаеммые валюты в стране пользователя
         */
        function is_valid_for_use()
        {
            if (!in_array(get_option('woocommerce_currency'), array('UAH', 'KZT'))) {
                return false;
            }
 
            return true;
        }

        /**
         * Параметры панели администратора
         *
         **/
        public function admin_options()
        {
            ?>
            <h3><?php _e('Prostoplateg.kz', 'woocommerce'); ?></h3>
            <p><?php _e('Настройка приема электронных платежей через ProstoPlateg.kz', 'woocommerce'); ?></p>

            <?php if ($this->is_valid_for_use()) : ?>

            <table class="form-table">

                <?php
                // Создание HTML-кода для формы настроек
                $this->generate_settings_html();
                ?>
            </table><!--/.form-table-->

        <?php else : ?>
            <div class="inline error"><p>
                    <strong><?php _e('Шлюз отключен', 'woocommerce'); ?></strong>: <?php _e('ProstoPlateg.kz валюта вашего магазина должна быть KZT или UAH.', 'woocommerce'); ?>
                </p></div>
            <?php
        endif;
        } // Конец admin_options()

        /**
         * Поля формы настройки шлюза
         *
         * @access public
         * @return void
         */
        function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Включить/Выключить', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Включен', 'woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Название', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Это название, которое пользователь видит во время проверки.', 'woocommerce'),
                    'default' => __('Prostoplateg', 'woocommerce')
                ),
                'MNT_ID' => array(
                    'title' => __('MERCHANT_INFO', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Пожалуйста введите MERCHANT_INFO — уникальный номер торговой точки в системе ProstoPlateg.kz (целое положительное число).', 'woocommerce'),
                    'default' => '7777777'
                ),
                'MNT_DATAINTEGRITY_CODE' => array(
                     'title' => __('SECRETCODE', 'woocommerce'),
                     'type' => 'text',
                     'description' => __('Пожалуйста введите $SECRETCODE — это строка-пароль, который известен только торговой точке и платежному шлюзу.', 'woocommerce'),
                     'default' => '******'
                ),
                'MNT_URL' => array(
                     'title' => __('URL сервера оплаты', 'woocommerce'),
                     'type' => 'select',
                     'options' => array(
                         'https://balance.prostoplateg.kz/sale.php' => 'prostoplateg.kz',
                         'https://money.ua/sale.php' => 'money.ua',
                     ),
                    'description' => __('Пожалуйста выберите URL сервера оплаты.', 'woocommerce'),
                     'default' => 'https://balance.prostoplateg.kz/sale.php'
                 ),
                 'MNT_TEST_MODE' => array(
                     'title' => __('Тестовый режим', 'woocommerce'),
                     'type' => 'checkbox',
                     'label' => __('Включен', 'woocommerce'),
                     'description' => __('В этом режиме плата за товар не снимается.', 'woocommerce'),
                     'default' => 'no'
                 ),
                 'PAYMENT_RULE' => array(
                     'title' => __('Коммисия за счет покупателя', 'woocommerce'),
                     'type' => 'checkbox',
                     'label' => __('Включен', 'woocommerce'),
                     'description' => __('В этом режиме оплата за коммиссию снимается за счет покупателя', 'woocommerce'),
                     'default' => 'yes'
                 ),
                 'debug' => array(
                    'title' => __('Debug', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Включить логирование (<code>woocommerce/logs/prostoplateg.txt</code>)', 'woocommerce'),
                    'default' => 'no'
                 ),
                 'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Описанием метода оплаты которое клиент будет видеть на вашем сайте.', 'woocommerce'),
                    'default' => 'Оплата с помощью ProstoPlateg.kz'
                 ),
                 'instructions' => array(
                    'title' => __('Instructions', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Инструкции, которые будут добавлены на страницу благодарностей.', 'woocommerce'),
                    'default' => 'Оплата с помощью ProstoPlateg.kz'
                 ),
            );
        }

        /**
         * Дополнительная информация в форме выбора способа оплаты
         **/
        function payment_fields()
        {
            if ( isset($_GET['pay_for_order']) && ! empty($_GET['key']) )
            {
                $order = wc_get_order( wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) ) );
                $this->receipt_page($order->id);
            }
        }

        /**
         * Обработка платежа и возврат результата
         **/
        function process_payment($order_id)
        {
            $order = new WC_Order($order_id);

            return array(
                'result' => 'success',
                'redirect' => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay'))))
            );
        }

        function cleanProductName($value)
        {
            $result = preg_replace('/[^0-9a-zA-Zа-яА-Я ]/ui', '', htmlspecialchars_decode($value));
            $result = trim(mb_substr($result, 0, 12));
            return $result;
        }

        /**
         * Форма оплаты
         **/
        function receipt_page($order_id)
        {
            $order = new WC_Order($order_id);

            //получаем информацию о клиенте
            $order_data = $order->get_data();
            //получаем информацию о заказаных товарах
            $items = $order->get_items();
            $product_name='';
            foreach ( $items as $item ) {
                $product_name.= $item->get_name().' ';
              //$product_id = $item->get_product_id();
              //$product_variation_id = $item->get_variation_id();
            }

			if (trim($this->redirect_page) == '') {
				$redirect_page_url = $order->get_checkout_order_received_url();
			} else {
				$redirect_page_url = trim($this->redirect_page);
			}
            $MERCHANT_INFO     = $this->MNT_ID;                          //уникальный номер торговой точки в нашей системе (целое положительное число).
            $PAYMENT_RULE      = ($this->PAYMENT_RULE == 'yes') ? 2 : 1; //указание, на чей счет будет отнесена комиссия по транзакции
            $PAYMENT_AMOUNT    = number_format($order->order_total, 2, '.', '')*100;//сумма оплачиваемых товаров в тиынах, целое число больше нуля.
            $PAYMENT_ADDVALUE  = '';                                     //дополнительная информация клиента (для передачи каких-либо параметров торговой точки), 255 символов.
            $PAYMENT_INFO      = substr(transliterate($product_name), 0, -1);           //информация о товаре, 255 символов. Удаляем последний символ пробел и переводим в транслит.
            $PAYMENT_DELIVER   = $order_data['shipping']['address_1']." ".$order_data['shipping']['address_2'].", ".$order_data['shipping']['city'].", ".$order_data['shipping']['state'].", ".$order_data['shipping']['first_name']." ".$order_data['shipping']['last_name']; //информация о доставке, 255 символов.
            $PAYMENT_DELIVER=transliterate($PAYMENT_DELIVER);            //TRANSLIT
            $PAYMENT_ORDER     = $order_id;                              //уникальный номер заказа в системе торговой точки, проверяется системой на уникальность — в случае поступления заказа на оплату с уже существующим оплаченным номером — система не примет заказ, выдаст ошибку.
            $PAYMENT_VISA      = '';                                     //зарезервирован для параметров оплат картами Visa/MasterCard в случае пользования нашим интерфейсом.
            $PAYMENT_TESTMODE  = ($this->MNT_TEST_MODE == 'yes') ? 1 : 0;//признак режима тестирования
            $PAYMENT_RETURNRES = get_site_url().'/?wc-api=wc_prostoplateg';              //полный URL, на который будет возвращен результат транзакции.
            $PAYMENT_RETURN    = $redirect_page_url;                     //полный URL, на который будет возвращен клиент после оплаты в случае успешной оплаты.
            $PAYMENT_RETURNMET = 2;                                      //1-GET, 2-POST метод, который будет использован для возврата результата на URL возврата результата
            $PAYMENT_RETURNFAIL= $order->get_cancel_order_url();                 //полный URL, на который будет возвращен клиент после оплаты в случае неудачной оплаты.
            $SECRETCODE        = $this->MNT_DATAINTEGRITY_CODE;          //секретный код известный магазину и мерчанту

            //Процес оформления заказа уменьшаем на 1 шаг, для этого сгенерируем хэш для всех способов оплаты а потом через JavaScript пропишем в input
            $signature27 = md5("$MERCHANT_INFO:27:$PAYMENT_RULE:$PAYMENT_AMOUNT:$PAYMENT_ADDVALUE:$PAYMENT_INFO:$PAYMENT_DELIVER:$PAYMENT_ORDER:$PAYMENT_VISA:$PAYMENT_TESTMODE:$PAYMENT_RETURNRES:$PAYMENT_RETURN:$PAYMENT_RETURNMET:$SECRETCODE");
            $signature31 = md5("$MERCHANT_INFO:31:$PAYMENT_RULE:$PAYMENT_AMOUNT:$PAYMENT_ADDVALUE:$PAYMENT_INFO:$PAYMENT_DELIVER:$PAYMENT_ORDER:$PAYMENT_VISA:$PAYMENT_TESTMODE:$PAYMENT_RETURNRES:$PAYMENT_RETURN:$PAYMENT_RETURNMET:$SECRETCODE");
            $signature26 = md5("$MERCHANT_INFO:26:$PAYMENT_RULE:$PAYMENT_AMOUNT:$PAYMENT_ADDVALUE:$PAYMENT_INFO:$PAYMENT_DELIVER:$PAYMENT_ORDER:$PAYMENT_VISA:$PAYMENT_TESTMODE:$PAYMENT_RETURNRES:$PAYMENT_RETURN:$PAYMENT_RETURNMET:$SECRETCODE");
            $signature33 = md5("$MERCHANT_INFO:33:$PAYMENT_RULE:$PAYMENT_AMOUNT:$PAYMENT_ADDVALUE:$PAYMENT_INFO:$PAYMENT_DELIVER:$PAYMENT_ORDER:$PAYMENT_VISA:$PAYMENT_TESTMODE:$PAYMENT_RETURNRES:$PAYMENT_RETURN:$PAYMENT_RETURNMET:$SECRETCODE");
            $signature9 = md5("$MERCHANT_INFO:9:$PAYMENT_RULE:$PAYMENT_AMOUNT:$PAYMENT_ADDVALUE:$PAYMENT_INFO:$PAYMENT_DELIVER:$PAYMENT_ORDER:$PAYMENT_VISA:$PAYMENT_TESTMODE:$PAYMENT_RETURNRES:$PAYMENT_RETURN:$PAYMENT_RETURNMET:$SECRETCODE");
            $signature14 = md5("$MERCHANT_INFO:14:$PAYMENT_RULE:$PAYMENT_AMOUNT:$PAYMENT_ADDVALUE:$PAYMENT_INFO:$PAYMENT_DELIVER:$PAYMENT_ORDER:$PAYMENT_VISA:$PAYMENT_TESTMODE:$PAYMENT_RETURNRES:$PAYMENT_RETURN:$PAYMENT_RETURNMET:$SECRETCODE");
            $signature15 = md5("$MERCHANT_INFO:15:$PAYMENT_RULE:$PAYMENT_AMOUNT:$PAYMENT_ADDVALUE:$PAYMENT_INFO:$PAYMENT_DELIVER:$PAYMENT_ORDER:$PAYMENT_VISA:$PAYMENT_TESTMODE:$PAYMENT_RETURNRES:$PAYMENT_RETURN:$PAYMENT_RETURNMET:$SECRETCODE");
            $signature16 = md5("$MERCHANT_INFO:16:$PAYMENT_RULE:$PAYMENT_AMOUNT:$PAYMENT_ADDVALUE:$PAYMENT_INFO:$PAYMENT_DELIVER:$PAYMENT_ORDER:$PAYMENT_VISA:$PAYMENT_TESTMODE:$PAYMENT_RETURNRES:$PAYMENT_RETURN:$PAYMENT_RETURNMET:$SECRETCODE");
            $signature17 = md5("$MERCHANT_INFO:17:$PAYMENT_RULE:$PAYMENT_AMOUNT:$PAYMENT_ADDVALUE:$PAYMENT_INFO:$PAYMENT_DELIVER:$PAYMENT_ORDER:$PAYMENT_VISA:$PAYMENT_TESTMODE:$PAYMENT_RETURNRES:$PAYMENT_RETURN:$PAYMENT_RETURNMET:$SECRETCODE");
            $signature18 = md5("$MERCHANT_INFO:18:$PAYMENT_RULE:$PAYMENT_AMOUNT:$PAYMENT_ADDVALUE:$PAYMENT_INFO:$PAYMENT_DELIVER:$PAYMENT_ORDER:$PAYMENT_VISA:$PAYMENT_TESTMODE:$PAYMENT_RETURNRES:$PAYMENT_RETURN:$PAYMENT_RETURNMET:$SECRETCODE");
            $signature1 = md5("$MERCHANT_INFO:1:$PAYMENT_RULE:$PAYMENT_AMOUNT:$PAYMENT_ADDVALUE:$PAYMENT_INFO:$PAYMENT_DELIVER:$PAYMENT_ORDER:$PAYMENT_VISA:$PAYMENT_TESTMODE:$PAYMENT_RETURNRES:$PAYMENT_RETURN:$PAYMENT_RETURNMET:$SECRETCODE");
            $signature2 = md5("$MERCHANT_INFO:2:$PAYMENT_RULE:$PAYMENT_AMOUNT:$PAYMENT_ADDVALUE:$PAYMENT_INFO:$PAYMENT_DELIVER:$PAYMENT_ORDER:$PAYMENT_VISA:$PAYMENT_TESTMODE:$PAYMENT_RETURNRES:$PAYMENT_RETURN:$PAYMENT_RETURNMET:$SECRETCODE");
            $signature3 = md5("$MERCHANT_INFO:3:$PAYMENT_RULE:$PAYMENT_AMOUNT:$PAYMENT_ADDVALUE:$PAYMENT_INFO:$PAYMENT_DELIVER:$PAYMENT_ORDER:$PAYMENT_VISA:$PAYMENT_TESTMODE:$PAYMENT_RETURNRES:$PAYMENT_RETURN:$PAYMENT_RETURNMET:$SECRETCODE");
            $signature5 = md5("$MERCHANT_INFO:5:$PAYMENT_RULE:$PAYMENT_AMOUNT:$PAYMENT_ADDVALUE:$PAYMENT_INFO:$PAYMENT_DELIVER:$PAYMENT_ORDER:$PAYMENT_VISA:$PAYMENT_TESTMODE:$PAYMENT_RETURNRES:$PAYMENT_RETURN:$PAYMENT_RETURNMET:$SECRETCODE");
            $signature34 = md5("$MERCHANT_INFO:34:$PAYMENT_RULE:$PAYMENT_AMOUNT:$PAYMENT_ADDVALUE:$PAYMENT_INFO:$PAYMENT_DELIVER:$PAYMENT_ORDER:$PAYMENT_VISA:$PAYMENT_TESTMODE:$PAYMENT_RETURNRES:$PAYMENT_RETURN:$PAYMENT_RETURNMET:$SECRETCODE");

            $args = array(
                'MERCHANT_INFO'     => $MERCHANT_INFO,      //уникальный номер торговой точки в нашей системе (целое положительное число).
                'PAYMENT_RULE'      => $PAYMENT_RULE,       //указание, на чей счет будет отнесена комиссия по транзакции
                'PAYMENT_AMOUNT'    => $PAYMENT_AMOUNT,     //сумма оплачиваемых товаров в тиынах, целое число больше нуля.
                'PAYMENT_ADDVALUE'  => $PAYMENT_ADDVALUE,   //дополнительная информация клиента (для передачи каких-либо параметров торговой точки), 255 символов.
                'PAYMENT_INFO'      => $PAYMENT_INFO,       //информация о товаре, 255 символов.
                'PAYMENT_DELIVER'   => $PAYMENT_DELIVER,    //информация о доставке, 255 символов.
                'PAYMENT_ORDER'     => $PAYMENT_ORDER,      //уникальный номер заказа в системе торговой точки, проверяется системой на уникальность — в случае поступления заказа на оплату с уже существующим оплаченным номером — система не примет заказ, выдаст ошибку.
                'PAYMENT_VISA'      => $PAYMENT_VISA,       //зарезервирован для параметров оплат картами Visa/MasterCard в случае пользования нашим интерфейсом.
                'PAYMENT_TESTMODE'  => $PAYMENT_TESTMODE,   //признак режима тестирования:
                'PAYMENT_RETURNRES' => $PAYMENT_RETURNRES,  //полный URL, на который будет возвращен результат транзакции.
                'PAYMENT_RETURN'    => $PAYMENT_RETURN,     //полный URL, на который будет возвращен клиент после оплаты в случае успешной оплаты.
                'PAYMENT_RETURNMET' => $PAYMENT_RETURNMET,  //1-GET, 2-POST метод, который будет использован для возврата результата на URL возврата результата
                'PAYMENT_RETURNFAIL'=> $PAYMENT_RETURNFAIL  //полный URL, на который будет возвращен клиент после оплаты в случае неудачной оплаты.
            );

            $args_array = array();

            foreach ($args as $key => $value) {
                $args_array[] = '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" />';
            }
                $annotation =   '<p>' . __('Пожалуйста выберите способ оплаты и нажмите кнопку ОПЛАТИТЬ.', 'woocommerce') . '</p>';
                $form_html =    '<form action="' . esc_url($this->MNT_URL) . '" method="POST">' . "\n" .
                                    implode("\n", $args_array) .
                                    '
                                    <input type="hidden" name="PAYMENT_HASH" id="PAYMENT_HASH" value="0" />
                                    <div class="woocommerce-additional-fields__field-wrapper">
							<p class="form-row notes validate-required" id="order_comments_field" data-priority="">
							<span class="woocommerce-input-wrapper">';
              //для prostoplateg.kz
							if($this->MNT_URL=='https://balance.prostoplateg.kz/sale.php'){
                $form_html .='    
                
							    <input type="radio" class="input-radio" value="27" name="PAYMENT_TYPE" id="PAYMENT_TYPE_27" style="float: left;" onClick="javascript:document.getElementById(\'PAYMENT_HASH\').value=\''.$signature27.'\'" /><label for="PAYMENT_TYPE_27" class="radio">EKZT</label>
							    <input type="radio" class="input-radio" value="31" name="PAYMENT_TYPE" id="PAYMENT_TYPE_31" style="float: left;" onClick="javascript:document.getElementById(\'PAYMENT_HASH\').value=\''.$signature31.'\'" /><label for="PAYMENT_TYPE_31" class="radio">Termkz (Касса24, Qiwi)</label>
							    <input type="radio" class="input-radio" value="26" name="PAYMENT_TYPE" id="PAYMENT_TYPE_26" style="float: left;" onClick="javascript:document.getElementById(\'PAYMENT_HASH\').value=\''.$signature26.'\'" /><label for="PAYMENT_TYPE_26" class="radio">WebMoney WMK</label>
							    <input type="radio" class="input-radio" value="33" name="PAYMENT_TYPE" id="PAYMENT_TYPE_33" style="float: left;" onClick="javascript:document.getElementById(\'PAYMENT_HASH\').value=\''.$signature33.'\'" /><label for="PAYMENT_TYPE_33" class="radio">Visa/Master Card</label>
							    ';
                            }
              //для money.ua                            
							if($this->MNT_URL=='https://money.ua/sale.php'){
				        $form_html .='    
							    <input type="radio" class="input-radio" value="9" name="PAYMENT_TYPE" id="PAYMENT_TYPE_9" style="float: left;" onClick="javascript:document.getElementById(\'PAYMENT_HASH\').value=\''.$signature9.'\'" /><label for="PAYMENT_TYPE_9" class="radio">НСМЭП</label>
							    <input type="radio" class="input-radio" value="14" name="PAYMENT_TYPE" id="PAYMENT_TYPE_14" style="float: left;" onClick="javascript:document.getElementById(\'PAYMENT_HASH\').value=\''.$signature14.'\'" /><label for="PAYMENT_TYPE_14" class="radio">Терминалы приема наличных</label>
							    <input type="radio" class="input-radio" value="15" name="PAYMENT_TYPE" id="PAYMENT_TYPE_15" style="float: left;" onClick="javascript:document.getElementById(\'PAYMENT_HASH\').value=\''.$signature15.'\'" /><label for="PAYMENT_TYPE_15" class="radio">LiqPay-USD</label>
							    <input type="radio" class="input-radio" value="16" name="PAYMENT_TYPE" id="PAYMENT_TYPE_16" style="float: left;" onClick="javascript:document.getElementById(\'PAYMENT_HASH\').value=\''.$signature16.'\'" /><label for="PAYMENT_TYPE_16" class="radio">VISA/MASTERCARD LiqPay</label>
							    <input type="radio" class="input-radio" value="17" name="PAYMENT_TYPE" id="PAYMENT_TYPE_17" style="float: left;" onClick="javascript:document.getElementById(\'PAYMENT_HASH\').value=\''.$signature17.'\'" /><label for="PAYMENT_TYPE_17" class="radio">ПРИВАТ24-ГРН</label>
							    <input type="radio" class="input-radio" value="18" name="PAYMENT_TYPE" id="PAYMENT_TYPE_18" style="float: left;" onClick="javascript:document.getElementById(\'PAYMENT_HASH\').value=\''.$signature18.'\'" /><label for="PAYMENT_TYPE_18" class="radio">ПРИВАТ24-USD</label>
							    ';
                            }
               //ОБЩИЕ
              	$form_html .='             
							    <input type="radio" class="input-radio" value="1" name="PAYMENT_TYPE" id="PAYMENT_TYPE_1" style="float: left;" onClick="javascript:document.getElementById(\'PAYMENT_HASH\').value=\''.$signature1.'\'" /><label for="PAYMENT_TYPE_1" class="radio">WebMoney WMZ</label>
							    <input type="radio" class="input-radio" value="2" name="PAYMENT_TYPE" id="PAYMENT_TYPE_2" style="float: left;" onClick="javascript:document.getElementById(\'PAYMENT_HASH\').value=\''.$signature2.'\'"  /><label for="PAYMENT_TYPE_2" class="radio">WebMoney WMR</label>
						<!--	<input type="radio" class="input-radio" value="3" name="PAYMENT_TYPE" id="PAYMENT_TYPE_3" style="float: left;" onClick="javascript:document.getElementById(\'PAYMENT_HASH\').value=\''.$signature3.'\'" /><label for="PAYMENT_TYPE_3" class="radio">WebMoney WMU</label> -->
							    <input type="radio" class="input-radio" value="5" name="PAYMENT_TYPE" id="PAYMENT_TYPE_5" style="float: left;" onClick="javascript:document.getElementById(\'PAYMENT_HASH\').value=\''.$signature5.'\'" /><label for="PAYMENT_TYPE_5" class="radio">Яндекс.Деньги</label>
							    <input type="radio" class="input-radio" value="34" name="PAYMENT_TYPE" id="PAYMENT_TYPE_34" style="float: left;" onClick="javascript:document.getElementById(\'PAYMENT_HASH\').value=\''.$signature34.'\'" /><label for="PAYMENT_TYPE_34" class="radio">BTC (Bitcoin)</label>
							</span>
							</p>					
							</div>
	</div>
	<input type="submit" class="button alt" id="submit_prostoplateg_payment_form" value="' . __('Оплатить', 'woocommerce') . '" name="B1" /> <a class="button cancel" href="' . $order->get_cancel_order_url() . '">' . __('Вернуться в корзину', 'woocommerce') . '</a>' . "\n" .
                                '</form>';
            echo $annotation.$form_html;
        }

        /**
         * Проверка обратного ответа
         **/
        function check_return_response()
        {
            global $woocommerce;
            print_r($_REQUEST);
            $success = isset($_POST['RETURN_HASH']);
            if ($success) {
                $SECRETCODE = $this->MNT_DATAINTEGRITY_CODE;
                $RETURN_MERCHANT = $_POST['RETURN_MERCHANT'];
                $RETURN_ADDVALUE = $_POST['RETURN_ADDVALUE'];
                $RETURN_CLIENTORDER = $_POST['RETURN_CLIENTORDER'];
                $RETURN_AMOUNT = $_POST['RETURN_AMOUNT'];
                $RETURN_COMISSION = $_POST['RETURN_COMISSION'];
                $RETURN_UNIQ_ID = $_POST['RETURN_UNIQ_ID'];
                $TEST_MODE = $_POST['TEST_MODE'];
                $PAYMENT_DATE = $_POST['PAYMENT_DATE'];
                $RETURN_RESULT = $_POST['RETURN_RESULT'];
                $RETURN_HASH = $_POST['RETURN_HASH'];

                $generated_HASH = md5("$RETURN_MERCHANT:$RETURN_ADDVALUE:$RETURN_CLIENTORDER:$RETURN_AMOUNT:$RETURN_COMISSION:$RETURN_UNIQ_ID:$TEST_MODE:$PAYMENT_DATE:$SECRETCODE:$RETURN_RESULT");
                if ($RETURN_HASH != $generated_HASH) wp_die('IPN Request Failure');

                $order = new WC_Order($RETURN_CLIENTORDER);
                if ($RETURN_RESULT == '20') {
                    $order->update_status('processing', __('Заказ оплачен (оплата получена)', 'woocommerce'));
                    $order->add_order_note(__('Клиент оплатил свой заказ', 'woocommerce'));
                    $woocommerce->cart->empty_cart();
                    echo 'OK';
                } else {
                    $order->update_status('failed', __('Оплата не была получена', 'woocommerce'));
                    wp_redirect($order->get_cancel_order_url());
                    exit;
                }
            } else {
                wp_die('IPN Request Failure');
            }
        }
    }

    /**
     * Добавляем шлюз в WooCommerce
     **/
    function add_prostoplateg_gateway($methods)
    {
        $methods[] = 'WC_Prostoplateg';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'add_prostoplateg_gateway');
}
?>
