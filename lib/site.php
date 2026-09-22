<?php

require_once __DIR__ . DIRECTORY_SEPARATOR . 'env.php';

function quantlab_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function quantlab_request_origin(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return ($https ? 'https://' : 'http://') . $host;
}

function quantlab_site_url(): string
{
    $configured = rtrim(quantlab_env('SITE_URL'), '/');
    if ($configured !== '') {
        return $configured;
    }
    return rtrim(quantlab_request_origin(), '/');
}

function quantlab_site_email(): string
{
    $email = quantlab_env('SITE_EMAIL', quantlab_env('SMTP_FROM', 'info@amquantlab.ru'));
    return $email !== '' ? $email : 'info@amquantlab.ru';
}

function quantlab_inn(): string
{
    return '165504227018';
}

function quantlab_legal_name(): string
{
    $name = trim(quantlab_env('LEGAL_NAME'));
    return $name !== '' ? $name : 'Самозанятый';
}

function quantlab_legal_ogrnip(): string
{
    return trim(quantlab_env('LEGAL_OGRNIP'));
}

function quantlab_legal_address(): string
{
    return trim(quantlab_env('LEGAL_ADDRESS'));
}

function quantlab_legal_phone(): string
{
    return trim(quantlab_env('LEGAL_PHONE'));
}

function quantlab_offer_date(): string
{
    return '23.09.2026';
}

function quantlab_offer_url(): string
{
    return quantlab_abs_url('/offer/');
}

function quantlab_privacy_url(): string
{
    return quantlab_abs_url('/privacy/');
}

function quantlab_render_legal_requisites(): void
{
    $ogrnip = quantlab_legal_ogrnip();
    $address = quantlab_legal_address();
    $phone = quantlab_legal_phone();
    ?>
        <div class="glass pad legal-card">
          <p class="eyebrow">Реквизиты</p>
          <dl class="legal-dl">
            <dt>Исполнитель</dt>
            <dd><?= quantlab_h(quantlab_legal_name()) ?></dd>
            <dt>Коммерческое обозначение</dt>
            <dd>AM QuantLab</dd>
            <dt>ИНН</dt>
            <dd><?= quantlab_h(quantlab_inn()) ?></dd>
            <?php if ($ogrnip !== ''): ?>
            <dt>ОГРНИП</dt>
            <dd><?= quantlab_h($ogrnip) ?></dd>
            <?php endif; ?>
            <?php if ($address !== ''): ?>
            <dt>Адрес</dt>
            <dd><?= quantlab_h($address) ?></dd>
            <?php endif; ?>
            <?php if ($phone !== ''): ?>
            <dt>Телефон</dt>
            <dd><a href="tel:<?= quantlab_h(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= quantlab_h($phone) ?></a></dd>
            <?php endif; ?>
            <dt>Email</dt>
            <dd><a href="mailto:<?= quantlab_h(quantlab_site_email()) ?>"><?= quantlab_h(quantlab_site_email()) ?></a></dd>
            <dt>Telegram</dt>
            <dd><a href="<?= quantlab_h(quantlab_telegram_url()) ?>" target="_blank" rel="noopener">@where_is_Lebowskis_money</a></dd>
            <dt>Сайт</dt>
            <dd><a href="<?= quantlab_h(quantlab_abs_url('/')) ?>"><?= quantlab_h(quantlab_abs_url('/')) ?></a></dd>
          </dl>
        </div>
    <?php
}

function quantlab_render_offer_prose(): void
{
    $email = quantlab_site_email();
    $inn = quantlab_inn();
    $name = quantlab_legal_name();
    $canonical = quantlab_offer_url();
    ?>
          <h2>1. Общие положения</h2>
          <p>1.1. Этот документ — публичная оферта в смысле статей 435 и 437 Гражданского кодекса РФ. Исполнитель предлагает любому дееспособному лицу заключить договор на условиях ниже.</p>
          <p>1.2. Исполнитель — <?= quantlab_h($name) ?>, ИНН <?= quantlab_h($inn) ?>, действующий под коммерческим обозначением AM QuantLab.</p>
          <p>1.3. Заказчик — физическое или юридическое лицо, которое оформило заявку на сайте <?= quantlab_h(quantlab_abs_url('/')) ?> и/или оплатило услугу.</p>
          <p>1.4. Акцепт оферты — оплата заказа банковской картой, через СБП или иным способом на сайте, либо оплата выставленного счёта. С этого момента договор считается заключённым без бумажного подписания.</p>
          <p>1.5. Оплачивая заказ или нажимая кнопку оплаты, Заказчик подтверждает, что прочитал оферту и <a href="/privacy/" data-privacy>политику конфиденциальности</a>, согласен с ними и даёт поручение на оплату.</p>
          <p>1.6. Актуальная редакция всегда открыта по адресу <a href="<?= quantlab_h($canonical) ?>"><?= quantlab_h($canonical) ?></a>. Для уже оплаченного заказа действует редакция на дату оплаты.</p>

          <h2>2. Предмет договора</h2>
          <p>2.1. Исполнитель оказывает возмездные услуги и выполняет работы в сфере алготрейдинга и финтеха:</p>
          <ul>
            <li>разработка торговых роботов и алгоритмов под официальные API Финам, Тинькофф Инвестиции, Bybit, OKX, Binance и другие площадки по согласованию;</li>
            <li>передача доступа к готовому решению из <a href="/robots/">каталога</a> — исходный код, инструкция, помощь с запуском в объёме, указанном на странице робота;</li>
            <li>сопутствующие финтех-сервисы: кабинеты, дашборды, интеграции, сопровождение, если это отдельно согласовано и оплачено.</li>
          </ul>
          <p>2.2. Исполнитель не является брокером, управляющим, инвестиционным советником и не принимает деньги в доверительное управление. Материалы сайта не являются индивидуальной инвестиционной рекомендацией.</p>
          <p>2.3. Исполнитель не обещает доходность, не продаёт сигналы и не компенсирует убыток на счёте Заказчика. Результат торговли зависит от рынка, брокера, риска и действий Заказчика.</p>
          <p>2.4. Ключи API, пароли и доступы к брокеру Заказчик создаёт сам и передаёт только в том объёме, который нужен для задачи. Seed-фразы и пароли от кошельков присылать нельзя.</p>

          <h2>3. Цена и порядок оплаты</h2>
          <p>3.1. Цена готового робота указана на его странице в каталоге. Цена кастомной разработки согласуется в переписке и фиксируется в счёте или письме Исполнителя. Ориентир кастомной разработки алгоритма — от 20&nbsp;000 ₽, если логика входа, стопа и цели уже сформулирована.</p>
          <p>3.2. Цена на сайте и в счёте — в рублях РФ. Исполнитель применяет налог на профессиональный доход (самозанятый). НДС не выделяется.</p>
          <p>3.3. Заказчик оплачивает 100% стоимости до начала работ, если в счёте не согласован иной график (например, предоплата и этап).</p>
          <p>3.4. Онлайн-оплата принимается банковскими картами платёжных систем «Мир», Visa, Mastercard, через СБП и другими способами, которые покажет платёжный сервис на странице оплаты. Платёж обрабатывает платёжный агрегатор. Данные карты Исполнитель не хранит.</p>
          <p>3.5. После успешной оплаты Заказчик получает чек самозанятого из сервиса «Мой налог» на указанный email, если это настроено у Исполнителя.</p>
          <p>3.6. Обязанность Заказчика по оплате считается исполненной, когда деньги поступили платёжному агрегатору или на счёт Исполнителя.</p>
          <p>3.7. Исполнитель вправе отказать в заказе и вернуть оплату, если задача незаконна, требует обойти API брокера, строить кликер терминала или иным способом нарушает правила площадки.</p>

          <h2>4. Срок и порядок оказания услуг</h2>
          <p>4.1. Готовый робот. После оплаты Исполнитель направляет материалы и инструкцию на email или в Telegram Заказчика в срок, указанный на странице робота, а если срок не указан — в течение 3 рабочих дней. Подключение к счёту Заказчика выполняется только если это прямо входит в описание товара или оплачено отдельно.</p>
          <p>4.2. Кастомная разработка. Срок считается с рабочего дня, следующего за оплатой и получением вводных: площадка, инструмент, правила входа и выхода, лимиты риска. Типовой срок — от 2 рабочих дней, если логика ясна. Сложный контур и кабинет согласуются отдельно.</p>
          <p>4.3. Результат передаётся электронно: репозиторий, архив, инструкция, доступ. Бумажный носитель и курьерская доставка не используются.</p>
          <p>4.4. Услуга считается оказанной, когда Исполнитель передал результат по пункту 4.3 или направил уведомление о готовности на контакт Заказчика. Если Заказчик не отвечает 10 календарных дней, результат считается принятым.</p>
          <p>4.5. Заказчик обеспечивает рабочий доступ к API и тестирует результат на своём контуре. Исполнитель не обязан держать боевой счёт Заказчика.</p>

          <h2>5. Интеллектуальная собственность</h2>
          <p>5.1. Готовый робот из каталога передаётся на условиях простой (неисключительной) лицензии: Заказчик может использовать решение на своих счетах. Перепродажа, публикация исходного кода и передача третьим лицам без письменного согласия Исполнителя запрещены.</p>
          <p>5.2. По кастомной разработке Заказчик получает право использовать созданный под него код в своих целях. Типовые библиотеки, каркас, общие модули и ноу-хау остаются у Исполнителя, если иное не согласовано письменно.</p>
          <p>5.3. Исполнитель вправе показывать факт сотрудничества и обезличенное описание задачи в портфолио, если Заказчик письменно не запретил это.</p>

          <h2>6. Возврат оплаты</h2>
          <p>6.1. Если Исполнитель ещё не передал результат и не начал работу — Заказчик может отказаться от договора и получить обратно уплаченную сумму.</p>
          <p>6.2. Если готовый робот передан, но существенно не соответствует описанию на странице заказа, Заказчик вправе потребовать безвозмездного устранения недостатков либо возврата в течение 14 календарных дней с передачи, письменно описав расхождение.</p>
          <p>6.3. После передачи результата не возвращается плата за услуги, которые Заказчик успел принять, и не компенсируются убытки торговли, комиссии брокера, проскальзывание и изменение рынка.</p>
          <p>6.4. По кастомной разработке оплата за уже выполненный этап не возвращается. Неиспользованный остаток предоплаты возвращается, если Исполнитель ещё не приступил к следующему этапу.</p>
          <p>6.5. Возврат идёт тем же способом, которым пришла оплата, в течение 10 рабочих дней после согласия Исполнителя на возврат. Срок зачисления зависит от банка Заказчика и платёжного сервиса.</p>
          <p>6.6. Заявление на возврат направляется на <a href="mailto:<?= quantlab_h($email) ?>"><?= quantlab_h($email) ?></a> с темой «Возврат», номером платежа или датой оплаты и причиной.</p>

          <h2>7. Ответственность</h2>
          <p>7.1. Исполнитель отвечает за соответствие результата описанию на сайте или согласованному заданию. Исполнитель не отвечает за сбои брокера и биржи, отзыв API, блокировку ключей, действия третьих лиц и решения Заказчика по риску.</p>
          <p>7.2. Совокупная ответственность Исполнителя по договору ограничена суммой, фактически уплаченной Заказчиком за конкретный заказ.</p>
          <p>7.3. Стороны не отвечают за невозможность исполнения из‑за обстоятельств непреодолимой силы: сбои связи, аварии у хостинга или платёжного сервиса, изменения закона, недоступность API площадки.</p>
          <p>7.4. Сайт и алгоритмы предоставляются «как есть» в части рыночного результата. Прошлая доходность в кейсах не гарантирует будущий результат.</p>

          <h2>8. Персональные данные</h2>
          <p>8.1. Заказчик даёт согласие на обработку персональных данных, указанных в заявке и при оплате, для исполнения договора, отправки чека и связи по заказу.</p>
          <p>8.2. Состав данных, сроки и права Заказчика описаны в <a href="/privacy/" data-privacy>политике конфиденциальности</a>.</p>
          <p>8.3. Платёжные реквизиты карты обрабатывает платёжный агрегатор. Исполнитель получает сведения об успехе или отказе платежа, сумму и идентификатор операции.</p>

          <h2>9. Изменение оферты и споры</h2>
          <p>9.1. Исполнитель может изменить оферту, опубликовав новую редакцию на этой странице. Дата редакции указана в начале документа.</p>
          <p>9.2. Претензии направляются на <a href="mailto:<?= quantlab_h($email) ?>"><?= quantlab_h($email) ?></a>. Срок ответа — 10 рабочих дней.</p>
          <p>9.3. Если спор не урегулирован, он рассматривается в суде по правилам ГПК РФ или АПК РФ.</p>
          <p>9.4. К договору применяется право Российской Федерации.</p>

          <h2>10. Контакты для оплаты и претензий</h2>
          <p>Почта: <a href="mailto:<?= quantlab_h($email) ?>"><?= quantlab_h($email) ?></a><br />
          Telegram: <a href="<?= quantlab_h(quantlab_telegram_url()) ?>" target="_blank" rel="noopener"><?= quantlab_h(quantlab_telegram_url()) ?></a><br />
          Документ: <a href="<?= quantlab_h($canonical) ?>"><?= quantlab_h($canonical) ?></a></p>
    <?php
}

function quantlab_render_privacy_prose(): void
{
    $email = quantlab_site_email();
    $inn = quantlab_inn();
    ?>
          <p>Политика действует вместе с <a href="/offer/" data-offer>публичной офертой</a> и относится к сайту <?= quantlab_h(quantlab_abs_url('/')) ?>.</p>

          <h2>1. Кто обрабатывает данные</h2>
          <p>Оператор — <?= quantlab_h(quantlab_legal_name()) ?>, коммерческое обозначение AM QuantLab, ИНН <?= quantlab_h($inn) ?>. Вопросы по данным: <a href="mailto:<?= quantlab_h($email) ?>"><?= quantlab_h($email) ?></a> или <a href="<?= quantlab_h(quantlab_telegram_url()) ?>" target="_blank" rel="noopener">Telegram</a>.</p>

          <h2>2. Какие данные собираем</h2>
          <ul>
            <li>из заявки: имя, Telegram или email, выбранный рынок, текст задачи;</li>
            <li>из переписки: то, что вы сами присылаете на почту или в Telegram;</li>
            <li>технические: IP, cookie, страницы просмотра — через хостинг и Яндекс Метрику;</li>
            <li>при оплате: факт платежа, сумма, идентификатор операции, email для чека. Номер карты и CVV обрабатывает платёжный агрегатор, мы их не получаем и не храним.</li>
          </ul>

          <h2>3. Зачем обрабатываем</h2>
          <p>Чтобы ответить на обращение, оценить задачу, принять оплату, отправить чек, передать результат заказа и вести претензии. Рекламные рассылки без отдельного согласия не отправляем.</p>

          <h2>4. Правовая основа</h2>
          <p>Согласие — отправка формы, сообщение в Telegram или оплата. Исполнение договора — если вы заказываете работу или готового робота. Закон 152-ФЗ и, при оплате, чек самозанятого из сервиса «Мой налог».</p>

          <h2>5. Кому передаём</h2>
          <p>Данные не продаём. Они могут быть доступны сервисам, без которых нельзя принять заявку или платёж: хостинг сайта, почта, Telegram, платёжный агрегатор и сервис «Мой налог». API брокеров и бирж используем только если вы сами даёте ключи для проекта.</p>

          <h2>6. Срок хранения</h2>
          <p>Заявки и данные оплаты храним, пока идёт переписка и исполнение заказа, затем — в сроки, нужные для претензий, бухгалтерии и кассовых чеков, либо до отзыва согласия, если закон не требует хранить дольше.</p>

          <h2>7. Ваши права</h2>
          <p>Вы можете запросить сведения о своих данных, уточнить их, отозвать согласие или попросить удалить обращение. Напишите на <a href="mailto:<?= quantlab_h($email) ?>"><?= quantlab_h($email) ?></a>. Отзыв согласия не отменяет обработку, которую закон требует сохранить.</p>

          <h2>8. Cookie и метрики</h2>
          <p>Сайт использует технические cookie и Яндекс Метрику: посещения, карта кликов и вебвизор. Рекламные пиксели сторонних сетей не подключаем.</p>

          <h2>9. Безопасность</h2>
          <p>Доступ к заявкам ограничен. Не присылайте в форме пароли, seed-фразы и секретные ключи API.</p>

          <p>Используя сайт, отправляя заявку или оплачивая заказ, вы подтверждаете, что ознакомились с этой политикой и <a href="/offer/" data-offer>публичной офертой</a>.</p>
    <?php
}

function quantlab_render_legal_modals(): void
{
    $date = quantlab_offer_date();
    $inn = quantlab_inn();
    ?>
    <div class="modal" id="offer-modal" hidden>
      <div class="modal-backdrop" data-legal-close></div>
      <div class="modal-card glass pad modal-card-legal" role="dialog" aria-modal="true" aria-labelledby="offer-title">
        <button class="modal-close" type="button" data-legal-close aria-label="Закрыть">×</button>
        <p class="eyebrow">Документ</p>
        <h2 id="offer-title">Публичная оферта</h2>
        <p class="modal-date">AM QuantLab · редакция от <?= quantlab_h($date) ?> · ИНН <?= quantlab_h($inn) ?></p>
        <?php quantlab_render_legal_requisites(); ?>
        <div class="prose modal-prose">
          <?php quantlab_render_offer_prose(); ?>
        </div>
        <button class="btn" type="button" data-legal-close>Понятно</button>
      </div>
    </div>
    <div class="modal" id="privacy-modal" hidden>
      <div class="modal-backdrop" data-legal-close></div>
      <div class="modal-card glass pad modal-card-legal" role="dialog" aria-modal="true" aria-labelledby="privacy-title">
        <button class="modal-close" type="button" data-legal-close aria-label="Закрыть">×</button>
        <p class="eyebrow">Документ</p>
        <h2 id="privacy-title">Политика конфиденциальности</h2>
        <p class="modal-date">AM QuantLab · редакция от <?= quantlab_h($date) ?> · ИНН <?= quantlab_h($inn) ?></p>
        <?php quantlab_render_legal_requisites(); ?>
        <div class="prose modal-prose">
          <?php quantlab_render_privacy_prose(); ?>
        </div>
        <button class="btn" type="button" data-legal-close>Понятно</button>
      </div>
    </div>
    <?php
}

function quantlab_founded_year(): int
{
    return 2026;
}

function quantlab_copyright_years(): string
{
    $from = quantlab_founded_year();
    $now = (int) date('Y');
    if ($now < $from) {
        $now = $from;
    }
    return $now > $from ? $from . '–' . $now : (string) $from;
}

function quantlab_footer_legal(): string
{
    return '© ' . quantlab_copyright_years() . ' AM QuantLab. Год создания: ' . quantlab_founded_year() . '. ИНН ' . quantlab_inn() . '.';
}

function quantlab_public_path(string $path): string
{
    $path = '/' . trim($path, '/');
    if ($path === '/') {
        return '/';
    }
    return $path . '/';
}

function quantlab_abs_url(string $path): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if ($path === '' || $path === '/') {
        return quantlab_site_url() . '/';
    }
    if (preg_match('/\.[a-z0-9]+$/i', $path)) {
        return quantlab_site_url() . '/' . ltrim($path, '/');
    }
    return quantlab_site_url() . quantlab_public_path($path);
}

function quantlab_request_path(): string
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    return $path ? $path : '/';
}

function quantlab_enforce_canonical(string $path): string
{
    $canonicalPath = quantlab_public_path($path);
    $canonical = quantlab_abs_url($canonicalPath);
    $requestPath = quantlab_request_path();
    $normalizedRequest = $requestPath === '/' ? '/' : rtrim($requestPath, '/') . '/';

    $redirect = $normalizedRequest !== $canonicalPath;

    $site = quantlab_site_url();
    $origin = rtrim(quantlab_request_origin(), '/');
    $httpHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $isLocal = str_starts_with($httpHost, '127.0.0.1') || str_starts_with($httpHost, 'localhost');
    if (!$isLocal && quantlab_env('SITE_URL') !== '' && strcasecmp($origin, $site) !== 0) {
        $redirect = true;
    }

    if ($redirect) {
        header('Location: ' . $canonical, true, 301);
        exit;
    }

    return $canonical;
}

function quantlab_json_ld(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return '<script type="application/ld+json">' . $json . '</script>';
}

function quantlab_render_crumbs(array $items): string
{
    $html = '<nav class="crumbs" aria-label="Хлебные крошки"><ol>';
    $ld = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [],
    ];
    $last = count($items) - 1;
    foreach ($items as $i => $item) {
        $url = quantlab_abs_url($item['path']);
        $name = (string) $item['name'];
        $html .= '<li>';
        if ($i < $last) {
            $html .= '<a href="' . quantlab_h($item['path'] === '/' ? '/' : quantlab_public_path($item['path'])) . '">' . quantlab_h($name) . '</a>';
        } else {
            $html .= '<span aria-current="page">' . quantlab_h($name) . '</span>';
        }
        $html .= '</li>';
        $ld['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $name,
            'item' => $url,
        ];
    }
    $html .= '</ol></nav>';
    return $html . quantlab_json_ld($ld);
}

function quantlab_icon_href(): string
{
    return '/favicon.ico';
}

function quantlab_icon_links(): void
{
    $svg = quantlab_h(quantlab_abs_url('/favicon.svg'));
    $png = quantlab_h(quantlab_abs_url('/favicon.png'));
    $png120 = quantlab_h(quantlab_abs_url('/favicon-120.png'));
    $ico = quantlab_h(quantlab_abs_url('/favicon.ico'));
    $apple = quantlab_h(quantlab_abs_url('/apple-touch-icon.png'));
    $manifest = quantlab_h(quantlab_abs_url('/manifest.json'));
    ?>
    <link rel="icon" href="<?= $svg ?>" type="image/svg+xml" sizes="any" />
    <link rel="icon" href="<?= $png ?>" type="image/png" sizes="120x120" />
    <link rel="icon" href="<?= $png120 ?>" type="image/png" sizes="120x120" />
    <link rel="shortcut icon" href="<?= $ico ?>" type="image/x-icon" />
    <link rel="apple-touch-icon" href="<?= $apple ?>" sizes="180x180" />
    <meta name="theme-color" content="#05070c" />
    <link rel="manifest" href="<?= $manifest ?>" />
    <?php
}

function quantlab_render_logo(string $href = '/', string $extra = ''): void
{
    ?>
          <a class="logo" href="<?= quantlab_h($href) ?>">
            <img class="logo-mark" src="/favicon.svg" width="28" height="28" alt="" />
            AM Quant<span>Lab</span><?= $extra !== '' ? quantlab_h($extra) : '' ?>
          </a>
    <?php
}

function quantlab_font_href(): string
{
    return 'https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500;600&family=Manrope:wght@400;500;600;700&family=Syne:wght@600;700;800&display=swap';
}

function quantlab_font_links(): void
{
    $href = quantlab_h(quantlab_font_href());
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link rel="stylesheet" href="<?= $href ?>" media="print" onload="this.media='all'" />
    <noscript><link rel="stylesheet" href="<?= $href ?>" /></noscript>
    <?php
}

function quantlab_head_verification(): void
{
    $yandex = quantlab_env('YANDEX_VERIFICATION', 'd94405cb4c18d9e3');
    $google = quantlab_env('GOOGLE_SITE_VERIFICATION', 'Z2TzFu1RkbL0doij_GukqPyVW3me4BjC7EH-Lw6bsDo');
    if ($yandex !== '') {
        echo '    <meta name="yandex-verification" content="' . quantlab_h($yandex) . '" />' . "\n";
    }
    if ($google !== '') {
        echo '    <meta name="google-site-verification" content="' . quantlab_h($google) . '" />' . "\n";
    }
}

function quantlab_author(): array
{
    return [
        'name' => 'Александр',
        'role' => 'Основатель AM QuantLab',
        'photo' => '/img/author.jpg',
        'years_dev' => '5+',
        'years_fintech' => '3',
        'return' => '30%',
        'mentor' => 'Илья Петров',
    ];
}

function quantlab_author_id(): string
{
    return rtrim(quantlab_site_url(), '/') . '/#author';
}

function quantlab_render_author(string $variant = 'section'): void
{
    $a = quantlab_author();
    $photo = quantlab_h($a['photo']);
    $name = quantlab_h($a['name']);
    $role = quantlab_h($a['role']);
    $compact = $variant === 'card';
    $class = $compact ? 'author-card glass pad' : 'author-panel glass pad';
    ?>
    <aside class="<?= $class ?>">
      <img class="author-photo" src="<?= $photo ?>" alt="<?= $name ?>, <?= $role ?>" width="640" height="960" <?= $compact ? 'loading="lazy"' : 'loading="eager"' ?> />
      <div class="author-copy">
        <?php if (!$compact): ?>
          <p class="eyebrow">Автор</p>
          <h2>Кто делает роботов</h2>
          <p class="author-name"><?= $name ?></p>
        <?php else: ?>
          <p class="author-name"><?= $name ?></p>
        <?php endif; ?>
        <p class="author-role"><?= $role ?></p>
        <p>
          Больше 5 лет в разработке. Три года собираю продукты для финтеха и торговые алгоритмы:
          роботы, API-контуры и журналы сделок под Финам, Тинькофф, Bybit, OKX и Binance.
        </p>
        <?php if (!$compact): ?>
          <p>
            Раньше работал разработчиком у алготрейдера Ильи Петрова: роботы и контуры под живой счёт, не под презентацию.
            Поэтому знаю, как выглядят риск, API и журнал сделок изнутри.
          </p>
          <p>
            Почему имеет смысл доверить задачу: пишу сам, хожу только в официальные API, не продаю сигналы и не обещаю чужую доходность.
          </p>
        <?php else: ?>
          <p>
            Раньше собирал роботов у алготрейдера Ильи Петрова. Пишу сам, официальные API, без обещания чужой доходности.
          </p>
        <?php endif; ?>
        <p>
          Есть своя стратегия: средняя годовая доходность около 30%. Это мой контур, не обещание по чужому счёту.
          Доходность в прошлом не гарантирует результат в будущем.
        </p>
        <ul class="author-stats">
          <li><strong><?= quantlab_h($a['years_dev']) ?></strong> лет в разработке</li>
          <li><strong><?= quantlab_h($a['years_fintech']) ?></strong> года финтех и алгоритмы</li>
          <li><strong>~<?= quantlab_h($a['return']) ?></strong> ср. годовых по своей стратегии</li>
        </ul>
        <?php if (!$compact): ?>
          <div class="hero-actions">
            <a class="btn" href="#contact" data-metrika-goal="contact">Обсудить задачу</a>
            <a class="btn btn-ghost" href="<?= quantlab_h(function_exists('quantlab_telegram_url') ? quantlab_telegram_url() : 'https://t.me/where_is_Lebowskis_money') ?>" target="_blank" rel="noopener">Telegram</a>
          </div>
        <?php endif; ?>
      </div>
    </aside>
    <?php
}

function quantlab_render_header_nav(array $opts = []): void
{
    $home = !empty($opts['home']);
    $active = (string) ($opts['active'] ?? '');
    $p = $home ? '' : '/';
    $blog = $home && !empty($opts['has_posts']) ? '#blog' : '/blog/';
    $cta = $home ? '#contact' : '/#contact';
    $item = static function (string $href, string $label, bool $current = false): void {
        $attr = $current ? ' aria-current="page"' : '';
        echo '<a href="' . quantlab_h($href) . '"' . $attr . '>' . quantlab_h($label) . '</a>';
    };
    ?>
        <nav class="nav" id="nav">
          <?php $item('/robots/', 'Роботы', $active === 'robots'); ?>
          <?php $item($p . '#case', 'Кейсы'); ?>
          <?php $item($blog, 'Блог', $active === 'blog'); ?>
          <div class="nav-more">
            <button class="nav-more-btn" type="button" aria-expanded="false" aria-haspopup="true">Ещё</button>
            <div class="nav-more-list">
              <?php $item($p . '#markets', 'Рынки'); ?>
              <?php $item($p . '#venues', 'Площадки'); ?>
              <?php $item($p . '#stack', 'Стек'); ?>
              <?php $item($p . '#algos', 'Продукты'); ?>
              <?php $item($p . '#dashboards', 'Дашборды'); ?>
              <?php $item($p . '#process', 'Процесс'); ?>
              <?php $item($p . '#author', 'Автор'); ?>
              <?php $item($p . '#faq', 'FAQ'); ?>
              <?php $item($p . '#contact', 'Контакт'); ?>
            </div>
          </div>
        </nav>
        <a class="btn btn-sm header-cta" href="<?= quantlab_h($cta) ?>" data-metrika-goal="contact">Заказать робота</a>
        <button class="burger" id="burger" type="button" aria-label="Открыть меню" aria-controls="nav" aria-expanded="false">
          <span></span><span></span><span></span>
        </button>
        <a class="mobile-cta" id="mobile-cta" href="<?= quantlab_h($cta) ?>" data-metrika-goal="contact">Заказать робота</a>
    <?php
}

function quantlab_render_start(array $meta): void
{
    $canonical = (string) $meta['canonical'];
    $title = (string) $meta['title'];
    $description = (string) ($meta['description'] ?? 'AM QuantLab — торговые роботы и финтех-сервисы.');
    $robots = (string) ($meta['robots'] ?? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1');
    if ($robots === 'index,follow') {
        $robots = 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';
    }
    $type = (string) ($meta['og_type'] ?? 'website');
    $keywords = trim((string) ($meta['keywords'] ?? ''));
    $image = trim((string) ($meta['image'] ?? ''));
    $imageAbs = $image !== '' ? quantlab_abs_url($image) : quantlab_abs_url('/favicon-512.png');
    $published = trim((string) ($meta['published_at'] ?? ''));
    $modified = trim((string) ($meta['modified_at'] ?? ''));
    $active = (string) ($meta['active'] ?? '');
    $extraHead = (string) ($meta['extra_head'] ?? '');
    $yandex = quantlab_env('YANDEX_VERIFICATION', 'd94405cb4c18d9e3');
    $google = quantlab_env('GOOGLE_SITE_VERIFICATION', 'Z2TzFu1RkbL0doij_GukqPyVW3me4BjC7EH-Lw6bsDo');

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="ru">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= quantlab_h($title) ?></title>
    <meta name="description" content="<?= quantlab_h($description) ?>" />
    <?php if ($keywords !== ''): ?>
    <meta name="keywords" content="<?= quantlab_h($keywords) ?>" />
    <?php endif; ?>
    <meta name="robots" content="<?= quantlab_h($robots) ?>" />
    <meta name="googlebot" content="<?= quantlab_h($robots) ?>" />
    <meta name="yandex" content="index, follow, max-image-preview:large" />
    <?php if ($yandex !== ''): ?>
    <meta name="yandex-verification" content="<?= quantlab_h($yandex) ?>" />
    <?php endif; ?>
    <?php if ($google !== ''): ?>
    <meta name="google-site-verification" content="<?= quantlab_h($google) ?>" />
    <?php endif; ?>
    <link rel="canonical" href="<?= quantlab_h($canonical) ?>" />
    <link rel="alternate" type="application/rss+xml" title="Блог AM QuantLab" href="<?= quantlab_h(quantlab_abs_url('rss.xml')) ?>" />
    <link rel="alternate" type="text/plain" title="llms.txt" href="<?= quantlab_h(quantlab_abs_url('llms.txt')) ?>" />
    <link rel="sitemap" type="application/xml" title="Sitemap" href="<?= quantlab_h(quantlab_abs_url('sitemap.xml')) ?>" />
    <meta property="og:type" content="<?= quantlab_h($type) ?>" />
    <meta property="og:locale" content="ru_RU" />
    <meta property="og:site_name" content="AM QuantLab" />
    <meta property="og:title" content="<?= quantlab_h($title) ?>" />
    <meta property="og:description" content="<?= quantlab_h($description) ?>" />
    <meta property="og:url" content="<?= quantlab_h($canonical) ?>" />
    <meta property="og:image" content="<?= quantlab_h($imageAbs) ?>" />
    <?php if ($image === ''): ?>
    <meta property="og:image:width" content="512" />
    <meta property="og:image:height" content="512" />
    <?php endif; ?>
    <meta property="og:image:alt" content="<?= quantlab_h($title) ?>" />
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="<?= quantlab_h($title) ?>" />
    <meta name="twitter:description" content="<?= quantlab_h($description) ?>" />
    <meta name="twitter:image" content="<?= quantlab_h($imageAbs) ?>" />
    <?php if ($published !== ''): ?>
    <meta property="article:published_time" content="<?= quantlab_h($published) ?>" />
    <?php endif; ?>
    <?php if ($modified !== ''): ?>
    <meta property="article:modified_time" content="<?= quantlab_h($modified) ?>" />
    <?php endif; ?>
    <?php quantlab_icon_links(); ?>
    <?php quantlab_font_links(); ?>
    <link rel="stylesheet" href="/css/styles.css" />
    <?= $extraHead ?>
    <?php if (function_exists('quantlab_render_metrika')) quantlab_render_metrika(); ?>
  </head>
  <body class="<?= quantlab_h((string) ($meta['body_class'] ?? 'page-inner')) ?>">
    <div class="noise" aria-hidden="true"></div>
    <div class="grid-bg" aria-hidden="true"></div>
    <div class="vignette" aria-hidden="true"></div>
    <div class="scroll-progress" id="scroll-progress" aria-hidden="true"></div>
    <div class="orb orb-a" aria-hidden="true"></div>
    <div class="orb orb-b" aria-hidden="true"></div>
    <div class="orb orb-c" aria-hidden="true"></div>
    <canvas id="fx-layer" class="fx-layer" aria-hidden="true"></canvas>
    <canvas id="candle-bg" class="candle-bg page-candle-bg" aria-hidden="true"></canvas>
    <header class="header" id="top">
      <div class="container header-inner">
        <div class="logo-block">
          <?php quantlab_render_logo('/'); ?>
          <span class="sys-status" aria-hidden="true"><span class="pulse"></span> live</span>
        </div>
        <?php quantlab_render_header_nav(['active' => $active]); ?>
      </div>
      <div class="nav-backdrop" id="nav-backdrop"></div>
    </header>
    <div class="ticker ticker-inner" aria-hidden="true">
      <div class="ticker-track">
        <div class="ticker-group">
          <span>FINAM</span><span>TINKOFF</span><span>BYBIT</span><span>OKX</span><span>BINANCE</span>
          <span>MOEX</span><span>BTC</span><span>ETH</span><span>CNYRUB</span>
        </div>
        <div class="ticker-group">
          <span>FINAM</span><span>TINKOFF</span><span>BYBIT</span><span>OKX</span><span>BINANCE</span>
          <span>MOEX</span><span>BTC</span><span>ETH</span><span>CNYRUB</span>
        </div>
      </div>
    </div>
    <main class="page-main">
    <?php
}

function quantlab_render_end(): void
{
    ?>
    </main>
    <footer class="footer">
      <div class="container footer-inner">
        <div class="footer-top">
          <div>
            <?php quantlab_render_logo('/'); ?>
            <p>Роботы на Node.js и Go. API Финам, Тинькофф Инвестиции, Bybit, OKX, Binance.</p>
          </div>
          <span class="sys-status"><span class="pulse"></span> systems online</span>
        </div>
        <p class="footer-links">
          <a href="/">Главная</a>
          <a href="/blog/">Блог</a>
          <a href="/robots/">Роботы</a>
          <a href="/#case">Кейсы</a>
          <a href="/#author">Автор</a>
          <a href="/#faq">FAQ</a>
          <a href="/#contact">Контакт</a>
          <a href="/offer/" data-offer>Оферта</a>
          <a href="/privacy/" data-privacy>Конфиденциальность</a>
          <a href="mailto:<?= quantlab_h(quantlab_site_email()) ?>"><?= quantlab_h(quantlab_site_email()) ?></a>
        </p>
        <p class="disclaimer">
          <?= quantlab_h(quantlab_footer_legal()) ?>
          Материал не является индивидуальной инвестиционной рекомендацией. Доходность в прошлом
          не гарантирует результат в будущем.
        </p>
      </div>
    </footer>
    <?php quantlab_render_legal_modals(); ?>
    <script src="/js/nav.js"></script>
    <script src="/js/motion.js"></script>
    <script src="/js/privacy.js"></script>
  </body>
</html>
    <?php
}
