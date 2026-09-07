(function () {
  if (document.getElementById("privacy-modal")) return;

  const html =
    '<div class="modal" id="privacy-modal" hidden>' +
    '<div class="modal-backdrop" data-privacy-close></div>' +
    '<div class="modal-card glass pad" role="dialog" aria-modal="true" aria-labelledby="privacy-title">' +
    '<button class="modal-close" type="button" data-privacy-close aria-label="Закрыть">×</button>' +
    '<p class="eyebrow">Документ</p>' +
    '<h2 id="privacy-title">Политика конфиденциальности</h2>' +
    '<p class="modal-date">AM QuantLab · редакция от 07.09.2026</p>' +
    '<div class="prose modal-prose">' +
    "<p>Настоящая политика описывает, как AM QuantLab обрабатывает персональные данные посетителей сайта и заявок на разработку торговых алгоритмов, роботов и финтех-сервисов.</p>" +
    "<h3>1. Кто обрабатывает данные</h3>" +
    "<p>Оператор — AM QuantLab. По вопросам обработки данных пишите на <a href=\"mailto:info@amquantlab.ru\">info@amquantlab.ru</a> или в Telegram: <a href=\"https://t.me/where_is_Lebowskis_money\" target=\"_blank\" rel=\"noopener\">@where_is_Lebowskis_money</a>.</p>" +
    "<h3>2. Какие данные собираем</h3>" +
    "<p>Если вы оставляете заявку, мы можем получить имя, Telegram или email, выбранный рынок и текст задачи. При переписке в Telegram обрабатываются данные, которые вы сами отправляете.</p>" +
    "<h3>3. Зачем обрабатываем</h3>" +
    "<p>Чтобы ответить на обращение, оценить задачу, согласовать стоимость и сроки, заключить договор и сопровождать разработку. Рекламные рассылки без согласия не отправляем.</p>" +
    "<h3>4. Правовая основа</h3>" +
    "<p>Обработка идёт на основании вашего согласия (отправка формы или сообщение в Telegram) и для исполнения договора, если вы заказываете работу.</p>" +
    "<h3>5. Кому передаём</h3>" +
    "<p>Данные не продаём. Они могут быть доступны сервисам, без которых нельзя принять заявку: хостинг сайта и Telegram. API бирж и брокеров (Финам, Bybit, OKX, Binance) используем только если вы сами даёте ключи и доступы для проекта.</p>" +
    "<h3>6. Срок хранения</h3>" +
    "<p>Заявки храним, пока ведётся переписка и исполнение заказа, затем — в сроки, нужные для претензий и бухгалтерии, либо до вашего отзыва согласия, если закон не требует хранить дольше.</p>" +
    "<h3>7. Ваши права</h3>" +
    "<p>Вы можете запросить сведения о ваших данных, уточнить их, отозвать согласие или попросить удалить обращение. Для этого напишите в Telegram.</p>" +
    "<h3>8. Файлы cookie и метрики</h3>" +
    "<p>Сайт использует технические cookie, нужные для работы страниц и админки. Отдельные рекламные пиксели не подключаем.</p>" +
    "<h3>9. Безопасность</h3>" +
    "<p>Доступы к заявкам ограничены. Не присылайте в форме пароли, seed-фразы и секретные ключи API.</p>" +
    "<p>Используя сайт и отправляя заявку, вы подтверждаете, что ознакомились с этой политикой.</p>" +
    "</div>" +
    '<button class="btn" type="button" data-privacy-close>Понятно</button>' +
    "</div></div>";

  document.body.insertAdjacentHTML("beforeend", html);

  const modal = document.getElementById("privacy-modal");

  const open = function () {
    modal.hidden = false;
    document.body.classList.add("modal-open");
  };

  const close = function () {
    modal.hidden = true;
    document.body.classList.remove("modal-open");
    if (location.hash === "#privacy") {
      history.replaceState(null, "", location.pathname + location.search);
    }
  };

  document.addEventListener("click", function (event) {
    const openBtn = event.target.closest("[data-privacy]");
    if (openBtn) {
      event.preventDefault();
      open();
      return;
    }
    if (event.target.closest("[data-privacy-close]")) {
      close();
    }
  });

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && !modal.hidden) close();
  });

  if (location.hash === "#privacy") open();
  window.addEventListener("hashchange", function () {
    if (location.hash === "#privacy") open();
  });
})();
