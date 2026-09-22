/*
 * QiangWang V2 deterministic Canvas document renderer.
 * The 2382×3369 canvases returned here are mounted as the preview and are later
 * saved directly as PNG / embedded directly into PDF. No DOM screenshot step.
 */
(() => {
  'use strict';

  const PAGE = Object.freeze({width: 794, height: 1123, outputWidth: 2382, outputHeight: 3369, scale: 3});
  const imageCache = new Map();
  const FONT_SANS = 'Arial, "Microsoft YaHei", "Noto Sans SC", sans-serif';
  const FONT_NARROW = '"Arial Narrow", Arial, sans-serif';
  const FONT_MONO = '"Courier New", monospace';

  function cloneData(value) {
    if (typeof structuredClone === 'function') return structuredClone(value || {});
    return JSON.parse(JSON.stringify(value || {}));
  }

  function createPage(background = '#ffffff') {
    const canvas = document.createElement('canvas');
    canvas.width = PAGE.outputWidth;
    canvas.height = PAGE.outputHeight;
    const ctx = canvas.getContext('2d', {alpha: false});
    ctx.fillStyle = background;
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.scale(PAGE.scale, PAGE.scale);
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    return {canvas, ctx};
  }

  function drawInDesign(page, designWidth, callback) {
    const scale = PAGE.width / designWidth;
    page.ctx.save();
    page.ctx.scale(scale, scale);
    callback(page.ctx, designWidth, PAGE.height / scale);
    page.ctx.restore();
    return page;
  }

  function font(ctx, size, weight = '400', family = FONT_SANS, style = '') {
    ctx.font = `${style ? style + ' ' : ''}${weight} ${size}px ${family}`;
  }

  function text(ctx, value, x, y, options = {}) {
    const str = value == null ? '' : String(value);
    ctx.save();
    font(ctx, options.size || 12, options.weight || '400', options.family || FONT_SANS, options.style || '');
    ctx.fillStyle = options.color || '#111111';
    ctx.textBaseline = options.baseline || 'top';
    ctx.textAlign = options.align || 'left';
    if (options.alpha != null) ctx.globalAlpha = options.alpha;
    if (options.maxWidth) ctx.fillText(str, x, y, options.maxWidth);
    else ctx.fillText(str, x, y);
    ctx.restore();
  }

  function measure(ctx, value, options = {}) {
    ctx.save();
    font(ctx, options.size || 12, options.weight || '400', options.family || FONT_SANS, options.style || '');
    const width = ctx.measureText(String(value == null ? '' : value)).width;
    ctx.restore();
    return width;
  }

  function fitText(ctx, value, x, y, maxWidth, options = {}) {
    let size = options.size || 12;
    const minSize = options.minSize || Math.max(7, size * 0.72);
    while (size > minSize && measure(ctx, value, {...options, size}) > maxWidth) size -= 0.5;
    text(ctx, value, x, y, {...options, size, maxWidth});
  }

  function tokenizeLine(line) {
    if (/\s/.test(line)) return line.split(/(\s+)/).filter(Boolean);
    return Array.from(line);
  }

  function wrapLines(ctx, value, maxWidth, options = {}) {
    const sourceLines = String(value == null ? '' : value).replace(/\r/g, '').split('\n');
    const lines = [];
    ctx.save();
    font(ctx, options.size || 12, options.weight || '400', options.family || FONT_SANS, options.style || '');
    sourceLines.forEach(source => {
      if (!source) {
        lines.push('');
        return;
      }
      const tokens = tokenizeLine(source);
      let line = '';
      tokens.forEach(token => {
        const candidate = line + token;
        if (line && ctx.measureText(candidate).width > maxWidth) {
          lines.push(line.trimEnd());
          line = token.trimStart();
        } else {
          line = candidate;
        }
      });
      lines.push(line.trimEnd());
    });
    ctx.restore();
    return lines;
  }

  function wrappedText(ctx, value, x, y, maxWidth, options = {}) {
    const lineHeight = options.lineHeight || (options.size || 12) * 1.45;
    const lines = wrapLines(ctx, value, maxWidth, options);
    const limit = options.maxLines || lines.length;
    lines.slice(0, limit).forEach((lineValue, index) => text(ctx, lineValue, x, y + index * lineHeight, options));
    return y + Math.min(lines.length, limit) * lineHeight;
  }

  function line(ctx, x1, y1, x2, y2, color = '#111111', width = 1) {
    ctx.save();
    ctx.beginPath();
    ctx.moveTo(x1, y1);
    ctx.lineTo(x2, y2);
    ctx.strokeStyle = color;
    ctx.lineWidth = width;
    ctx.stroke();
    ctx.restore();
  }

  function rect(ctx, x, y, width, height, options = {}) {
    ctx.save();
    if (options.fill) {
      ctx.fillStyle = options.fill;
      ctx.fillRect(x, y, width, height);
    }
    if (options.stroke) {
      ctx.strokeStyle = options.stroke;
      ctx.lineWidth = options.lineWidth || 1;
      ctx.strokeRect(x, y, width, height);
    }
    ctx.restore();
  }

  function rule(ctx, x, y, width, color = '#d8d8d8', thickness = 1) {
    line(ctx, x, y, x + width, y, color, thickness);
  }

  function stripHtml(markup) {
    if (!markup) return '';
    const el = document.createElement('div');
    el.innerHTML = String(markup).replace(/<br\s*\/?\s*>/gi, '\n');
    return (el.textContent || '').replace(/\u00a0/g, ' ').trim();
  }

  function htmlRoot(markup) {
    const source = String(markup || '');
    // Generated transaction fragments are intentionally raw <tr> elements.
    // Browsers discard those nodes when parsed in a <div>, so use table parsing
    // context unless the fragment already supplies its own <table> wrapper.
    const rawTableRows = /<tr[\s>]/i.test(source) && !/<table[\s>]/i.test(source);
    const root = document.createElement(rawTableRows ? 'table' : 'div');
    root.innerHTML = source;
    return root;
  }

  function parseTableRows(markup) {
    const root = htmlRoot(markup);
    return Array.from(root.querySelectorAll('tr')).map(row => ({
      className: row.className || '',
      cells: Array.from(row.querySelectorAll('th,td')).map(cell => {
        const copy = cell.cloneNode(true);
        copy.querySelectorAll('.type-sub, .col-gray').forEach(secondary => {
          secondary.replaceWith(document.createTextNode(`\n${(secondary.textContent || '').trim()}`));
        });
        return (copy.textContent || '').split('\n').map(lineValue => lineValue.replace(/\s+/g, ' ').trim()).filter(Boolean).join('\n');
      })
    })).filter(row => row.cells.length > 0);
  }

  function parseDivRows(markup, rowSelector, selectors) {
    const root = htmlRoot(markup);
    return Array.from(root.querySelectorAll(rowSelector)).map(row => selectors.map(selector => {
      const node = row.querySelector(selector);
      return node ? (node.textContent || '').replace(/\s+/g, ' ').trim() : '';
    }));
  }

  function loadImage(url) {
    if (imageCache.has(url)) return imageCache.get(url);
    const pending = new Promise((resolve, reject) => {
      const image = new Image();
      image.decoding = 'async';
      image.onload = () => resolve(image);
      image.onerror = () => reject(new Error(`无法加载 V2 素材：${url}`));
      image.src = url;
    });
    imageCache.set(url, pending);
    return pending;
  }

  function drawImage(ctx, image, x, y, width, height) {
    ctx.save();
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(image, x, y, width, height);
    ctx.restore();
  }

  function amount(value, fallback = '0.00') {
    const str = String(value == null || value === '' ? fallback : value);
    return str;
  }

  function drawSimpleTable(ctx, rows, x, y, widths, options = {}) {
    const rowHeight = options.rowHeight || 34;
    const headerHeight = options.headerHeight || rowHeight;
    const totalWidth = widths.reduce((sum, width) => sum + width, 0);
    const header = options.header || [];
    if (header.length) {
      rect(ctx, x, y, totalWidth, headerHeight, {fill: options.headerFill || '#eeeeee'});
      if (options.headerSeparatorColor) {
        let separatorX = x;
        widths.slice(0, -1).forEach(cellWidth => {
          separatorX += cellWidth;
          line(ctx, separatorX, y, separatorX, y + headerHeight, options.headerSeparatorColor, options.headerSeparatorWidth || 1);
        });
      }
      let cx = x;
      header.forEach((label, index) => {
        wrappedText(ctx, label, cx + 6, y + 6, widths[index] - 12, {size: options.headerSize || 10, weight: '700', lineHeight: 12, maxLines: 2, align: options.alignments?.[index] || 'left'});
        cx += widths[index];
      });
      y += headerHeight;
    }
    rows.forEach((row, rowIndex) => {
      const height = typeof options.rowHeightFor === 'function' ? options.rowHeightFor(row, rowIndex) : rowHeight;
      if (options.striped && rowIndex % 2 === 1) rect(ctx, x, y, totalWidth, height, {fill: options.stripeFill || '#f7f7f7'});
      let cx = x;
      row.forEach((cell, index) => {
        const align = options.alignments?.[index] || 'left';
        const tx = align === 'right' ? cx + widths[index] - 6 : align === 'center' ? cx + widths[index] / 2 : cx + 6;
        wrappedText(ctx, cell, tx, y + 7, widths[index] - 12, {size: options.size || 10, lineHeight: options.lineHeight || 13, maxLines: options.maxLines || 2, align});
        cx += widths[index];
      });
      rule(ctx, x, y + height, totalWidth, options.ruleColor || '#dddddd', 0.8);
      y += height;
    });
    return y;
  }

  function drawMoneseLogo(ctx, x, y) {
    ctx.save();
    ctx.translate(x, y);
    ctx.scale(40 / 48, 36 / 40);
    ctx.strokeStyle = '#28628a';
    ctx.lineWidth = 3.8;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    [
      'M3.5 34V8.5c0-4 4.8-6 7.7-3.1L24 18.4 36.8 5.3c2.9-2.9 7.7-.9 7.7 3.1V34',
      'M4.7 6.6 20.8 33c1.5 2.5 4.9 2.6 6.5.1L43.2 6.6',
      'M4.2 33.2c1.7 2 4.8 2.1 6.7.2L24 20.2l13.1 13.2c1.8 1.9 4.9 1.8 6.7-.2'
    ].forEach(path => ctx.stroke(new Path2D(path)));
    ctx.restore();
    text(ctx, 'monese', x + 48, y - 1, {size: 28, weight: '600', color: '#28628a', family: FONT_NARROW});
  }

  function renderMonese(data) {
    const page = createPage();
    drawInDesign(page, 848, (ctx, width, height) => {
      rect(ctx, 0, 0, width, height, {fill: '#ffffff'});
      drawMoneseLogo(ctx, 40, 47);

      text(ctx, data.customer_name, 470, 47, {size: 12, weight: '700', family: FONT_NARROW});
      text(ctx, data.address_street, 470, 65, {size: 12, family: FONT_NARROW});
      text(ctx, data.postal_code, 470, 83, {size: 12, family: FONT_NARROW});
      text(ctx, data.address_district, 470, 101, {size: 12, family: FONT_NARROW});

      text(ctx, 'IBAN', 642, 47, {size: 12, weight: '700', family: FONT_NARROW});
      text(ctx, data.iban, 642, 65, {size: 12, family: FONT_NARROW});
      text(ctx, 'BIC', 642, 101, {size: 12, weight: '700', family: FONT_NARROW});
      text(ctx, data.bic, 642, 119, {size: 12, family: FONT_NARROW});
      text(ctx, 'Monese kenn-nr.', 642, 152, {size: 12, weight: '700', family: FONT_NARROW});
      text(ctx, data.account_number, 642, 170, {size: 12, family: FONT_NARROW});

      text(ctx, 'EUR-Kontoauszug', 40, 274, {size: 34, weight: '400', family: FONT_NARROW});
      text(ctx, data.statement_period, 40, 323, {size: 19, family: FONT_NARROW});

      const sx = 40, sw = 423;
      let sy = 391;
      rule(ctx, sx, sy, sw, '#111111', 1.5);
      text(ctx, 'Neuer kontostand', sx, sy + 23, {size: 14, weight: '700', family: FONT_NARROW});
      text(ctx, `${data.currency || '€'}${amount(data.opening_balance)}`, sx + sw, sy + 23, {size: 14, weight: '700', align: 'right', family: FONT_NARROW});
      sy += 63;
      rule(ctx, sx, sy, sw, '#b7b7b7', 1);
      const details = [
        ['Zahlungseingänge', `+${data.currency || '€'}${amount(data.total_credits)}`],
        ['Zahlungsausgänge', `-${data.currency || '€'}${amount(data.total_debits)}`],
        ['Vorgemerkte zahlungen', `-${data.currency || '€'}0.00`]
      ];
      details.forEach((row, index) => {
        const yy = sy + 20 + index * 42;
        text(ctx, row[0], sx, yy, {size: 14, family: FONT_NARROW});
        text(ctx, row[1], sx + sw, yy, {size: 14, align: 'right', family: FONT_NARROW});
      });
      sy += 146;
      rule(ctx, sx, sy, sw, '#b7b7b7', 1);
      text(ctx, '"Neuer kontostand"', sx, sy + 21, {size: 14, weight: '700', family: FONT_NARROW});
      text(ctx, `${data.currency || '€'}${amount(data.closing_balance)}`, sx + sw, sy + 21, {size: 14, weight: '700', align: 'right', family: FONT_NARROW});

      const tx = 40, tw = 763, ty = 749;
      text(ctx, 'Transaktionen', tx, ty, {size: 24, family: FONT_NARROW});
      const tableY = ty + 63;
      rule(ctx, tx, tableY, tw, '#111111', 1.5);
      const columns = [153, 153, 250, 100, 107];
      const headers = ['Bearbeitungsdatum', 'Datum der zahlung', 'Beschreibung', '"Betrag"', '"Kontostand"'];
      let cx = tx;
      headers.forEach((header, index) => {
        const align = index >= 3 ? 'right' : 'left';
        const px = align === 'right' ? cx + columns[index] - 3 : cx + 3;
        fitText(ctx, header, px, tableY + 29, columns[index] - 8, {size: 14, minSize: 10.5, weight: '700', align, family: FONT_NARROW});
        cx += columns[index];
      });
      let rowY = tableY + 77;
      rule(ctx, tx, rowY, tw, '#111111', 1.5);
      const rows = parseDivRows(data.transactions, '.transaction-row', ['.date', '.payment-date', '.description', '.transaction-amount', '.balance']);
      rows.forEach((row, index) => {
        let colX = tx;
        row.forEach((cell, colIndex) => {
          const align = colIndex >= 3 ? 'right' : 'left';
          const px = align === 'right' ? colX + columns[colIndex] : colX;
          wrappedText(ctx, cell, px, rowY + 21, columns[colIndex] - (align === 'right' ? 0 : 12), {size: colIndex === 2 ? 12 : 14, lineHeight: 17, maxLines: 3, align, family: FONT_NARROW});
          colX += columns[colIndex];
        });
        rowY += 93;
        if (index === rows.length - 2) rule(ctx, tx, rowY, tw, '#c8c8c8', 1);
      });
    });
    return [page];
  }

  async function renderWise(data, variant) {
    const isUkStatement = variant === 'uk';
    const logo = await loadImage('assets/logos/wise-logo.png');
    const page = createPage();
    const ctx = page.ctx;
    const x = 75.6, right = PAGE.width - 75.6, contentWidth = right - x;
    let y = 75.6;
    drawImage(ctx, logo, x + 12, y + 22, 89, 21);
    y += 80;
    text(ctx, 'Wise Payments Ltd.', x, y, {size: 14, weight: '700'});
    y += 21;
    ['1st Floor, Worship Square, 65 Clifton Street', 'London', 'EC2A 4JE', 'United Kingdom'].forEach(value => {
      text(ctx, value, x, y, {size: 13, color: '#4a4a4a'});
      y += 19.5;
    });
    y += 45;
    text(ctx, `${data.wise_currency || 'EUR'} 对账单`, x, y, {size: 26, weight: '700'});
    y += 51;
    fitText(ctx, `${data.wise_period_start} [${data.wise_timezone}] - ${data.wise_period_end} [${data.wise_timezone}]`, x, y, contentWidth, {size: 15, weight: '700'});
    y += 34;
    text(ctx, `生成日期: ${data.wise_generated_date}`, x, y, {size: 13, color: '#666666'});
    y += 40;
    rule(ctx, x, y, contentWidth, '#e0e0e0');
    y += 16;

    text(ctx, '账户持有人', x, y, {size: 13, weight: '700'});
    const infoY = y + 23;
    [data.customer_name, data.address_unit, data.wise_city, data.wise_state, data.wise_postcode, data.wise_country].filter(Boolean).forEach((value, index) => text(ctx, value, x, infoY + index * 19.5, {size: 13}));
    const rx = x + contentWidth * 0.52;
    const infoColWidth = contentWidth * 0.225;
    if (isUkStatement) {
      text(ctx, '账号', rx, y, {size: 13, weight: '700'});
      fitText(ctx, data.account_number, rx, infoY, infoColWidth, {size: 13, minSize: 9});
      text(ctx, '英国排序代码 (Sort Code)', rx + contentWidth * 0.25, y, {size: 13, weight: '700'});
      fitText(ctx, data.sort_code, rx + contentWidth * 0.25, infoY, infoColWidth, {size: 13, minSize: 9});
      text(ctx, 'IBAN 代码', rx, y + 62, {size: 13, weight: '700'});
      fitText(ctx, data.wise_iban, rx, infoY + 62, infoColWidth, {size: 13, minSize: 9});
      text(ctx, 'Swift/BIC', rx + contentWidth * 0.25, y + 62, {size: 13, weight: '700'});
      fitText(ctx, data.wise_bic, rx + contentWidth * 0.25, infoY + 62, infoColWidth, {size: 13, minSize: 9});
    } else {
      text(ctx, 'IBAN 代码', rx, y, {size: 13, weight: '700'});
      fitText(ctx, data.wise_iban, rx, infoY, infoColWidth, {size: 13, minSize: 9});
      text(ctx, 'Swift/BIC', rx + contentWidth * 0.25, y, {size: 13, weight: '700'});
      fitText(ctx, data.wise_bic, rx + contentWidth * 0.25, infoY, infoColWidth, {size: 13, minSize: 9});
    }
    y += 145;
    rule(ctx, x, y, contentWidth, '#e0e0e0');
    y += 13;
    text(ctx, `${data.wise_currency}，${data.wise_balance_date} [${data.wise_timezone}]`, x, y + 9, {size: 16, weight: '700'});
    text(ctx, `${amount(data.wise_balance)} ${data.wise_currency}`, right, y + 9, {size: 16, weight: '700', align: 'right'});
    y += 57;
    rule(ctx, x, y, contentWidth, '#e0e0e0');
    y += 18;

    const widths = [contentWidth * 4 / 7, contentWidth / 7, contentWidth / 7, contentWidth / 7];
    let cx = x;
    ['描述', '汇入', '汇出', '金额'].forEach((label, index) => {
      const align = index ? 'right' : 'left';
      text(ctx, label, align === 'right' ? cx + widths[index] : cx, y, {size: 12, weight: '700', color: '#4a4a4a', align});
      cx += widths[index];
    });
    y += 31;
    rule(ctx, x, y, contentWidth, '#e0e0e0');
    y += 16;
    const rows = parseDivRows(data.wise_transactions || data.transactions, '.txn-row', ['.td-desc', '.td-in', '.td-out', '.td-bal']);
    rows.forEach(row => {
      let colX = x;
      row.forEach((cell, index) => {
        const align = index ? 'right' : 'left';
        text(ctx, cell, align === 'right' ? colX + widths[index] : colX, y, {size: 13, align});
        colX += widths[index];
      });
      y += 24;
    });

    const footerY = Math.max(y + 55, 880);
    wrappedText(ctx, 'Wise Payments Limited 以 Wise 名称交易，是由英国金融行为监管局 (FCA) 授权的电子货币机构，公司编号为 900507。Wise Payments Limited 通过 Companies House 在英格兰和威尔士注册，公司注册号为 7209813。电话: +44 (0) 203 6950 999', x, footerY, contentWidth, {size: 12, color: '#4a4a4a', lineHeight: 19, maxLines: 4});
    text(ctx, '需要帮助？ 请访问 wise.com/help', x, footerY + 92, {size: 12, color: '#4a4a4a'});
    text(ctx, `ref:${data.wise_ref || ''}`, x, PAGE.height - 75.6, {size: 10, color: '#666666'});
    text(ctx, '1/1', right, PAGE.height - 75.6, {size: 10, color: '#666666', align: 'right'});
    return [page];
  }

  async function renderKraken(data) {
    const logo = await loadImage('assets/logos/kraken-mark.png');
    const rowsPortfolio = parseTableRows(data.portfolio_table).map(row => row.cells);
    const rowsActivity = parseTableRows(data.transactions).map(row => row.cells);
    const pages = [createPage('#f4f4f4'), createPage('#f4f4f4')];

    pages.forEach((page, pageIndex) => drawInDesign(page, 1040, (ctx, width, height) => {
      rect(ctx, 0, 0, width, height, {fill: '#f4f4f4'});
      rect(ctx, 20, 20, 1000, height - 40, {fill: '#ffffff'});
      const x = 70, right = 970;
      drawImage(ctx, logo, x + 9, 71, 31, 24);
      text(ctx, 'krak', x + 54, 59, {size: 38, weight: '700'});
      text(ctx, `Monthly Statement: ${data.statement_month || ''}`, right, 60, {size: 11, align: 'right'});
      text(ctx, `All portfolio balances are recorded as of ${data.balance_date || ''} UTC`, right, 77, {size: 11, align: 'right'});

      if (pageIndex === 0) {
        text(ctx, 'Payward Services Limited and Payward Ltd.', x, 150, {size: 12, weight: '700'});
        text(ctx, '6th Floor, One London Wall,', x, 194, {size: 12});
        text(ctx, 'London, United Kingdom, EC2Y 5EB', x, 213, {size: 12});
        line(ctx, 520, 145, 520, 285, '#e0e0e0', 2);
        text(ctx, data.customer_name, 540, 150, {size: 12, weight: '700'});
        wrappedText(ctx, `${data.address_unit || ''}\n${data.address_district || ''}\n\nKraken Public ID:\n${data.kraken_public_id || ''}\n\nAccount ID:\n${data.account_number || ''}`, 540, 190, 360, {size: 12, lineHeight: 19});

        text(ctx, 'Portfolio', x, 340, {size: 14, weight: '700', color: '#e33f3e'});
        const portfolioWidths = [85, 90, 85, 105, 105, 85, 105, 105, 135];
        let y = drawSimpleTable(ctx, rowsPortfolio, x, 370, portfolioWidths, {
          header: ['Asset', 'Wallet', 'Open Qty', 'Open Price (GBP)', 'Open Value (GBP)', 'Close Qty', 'Close Price (GBP)', 'Close Value (GBP)', 'Net Change (GBP)'], headerFill: '#fadada', headerSeparatorColor: '#ffffff', headerSeparatorWidth: 2, headerHeight: 42, rowHeight: 38, size: 9, headerSize: 8.5, striped: true, alignments: ['left','left','right','right','right','right','right','right','right']
        });
        text(ctx, 'Activity', x, y + 30, {size: 14, weight: '700', color: '#e33f3e'});
        const activityY = y + 60;
        const activityWidths = [80, 95, 65, 75, 90, 85, 75, 85, 130, 120];
        drawSimpleTable(ctx, rowsActivity, x, activityY, activityWidths, {
          header: ['Date (UTC)', 'Type', 'Asset', 'Wallet', 'Amount', 'Price (GBP)', 'Fee (GBP)', 'Value (GBP)', 'Counter party', 'Reference'], headerFill: '#fadada', headerSeparatorColor: '#ffffff', headerSeparatorWidth: 2, headerHeight: 42, rowHeight: 42, size: 8.5, headerSize: 8, striped: true, alignments: ['left','left','left','left','right','right','right','right','left','left']
        });
      } else {
        text(ctx, 'Disclaimer:', x, 150, {size: 13, weight: '700', color: '#e33f3e'});
        let y = 190;
        const sections = [
          ['Your Kraken monthly account statement ("Statement") is an important document and contains a record of your Kraken account balances held with, and transaction activity facilitated by, your Kraken account servicing entities. Your Kraken account servicing entities depend on your location, as detailed in our Terms of Use: https://www.kraken.com/legal', false],
          ['Understanding this Statement:', true],
          ['This Statement is intended to provide you with a monthly snapshot of your account and should not be used for the purpose of tax reporting. This Statement is prepared from information Kraken believes to be reliable in order to provide you with this service.\n\nUse of this Statement is governed by Kraken\'s Terms of Use which sets out the relationship between you and Kraken.', false],
          ['Handling discrepancies and other issues relating to this Statement:', true],
          ['You must review this Statement carefully and notify us as soon as you become aware of any errors or omissions in this Statement, including any transactions that you did not authorize or that you do not recognise, or if you have any other questions or concerns. Failing to do so could impact whether you are entitled to a refund (for example, if there is an unauthorized transaction on your account). If you have any questions or concerns of this kind, please report them to https://support.kraken.com/hc/en-us.', false],
          ['Contact Kraken Support: https://support.kraken.com/hc/en-us\n\nActivity Types Explained: https://support.kraken.com/hc/en-us/articles/360001169383-How-to-interpret-Ledger-history-fields\n\nRates: Xe https://www.xe.com/', false],
          ['Krak Card is issued by Monavate Limited, authorised by the Financial Conduct Authority to carry on electronic money activities and related payment services (FRN: 901097).\n\nPayward Services Limited (company no. 12861311) is authorised by the Financial Conduct Authority to carry out electronic money activities under the Electronic Money Regulations 2011 (FRN: 1010381). E-money is not a bank deposit, is not covered by the Financial Services Compensation Scheme (FSCS), and is safeguarded in segregated accounts in accordance with the FCA\'s rules.\n\nPayward Ltd (company no. 08593670) is registered with the Financial Conduct Authority as a cryptoasset business pursuant to the Money Laundering Regulations 2017 (FRN: 928768). Cryptoasset services offered by Payward Ltd are unregulated and not within the jurisdiction of the Financial Ombudsman Service or subject to protection under the Financial Services Compensation Scheme. The value of cryptoassets can go down as well as up, gains may be subject to Capital Gains Tax and there may be extra charges when paying via credit card from your provider.\n\nThe registered office for both Payward Services Limited and Payward Ltd. is 6th Floor, One London Wall, London, United Kingdom, EC2Y 5EB.', false]
        ];
        sections.forEach(([value, heading]) => {
          y = wrappedText(ctx, value, x, y, 900, {size: heading ? 12 : 11, weight: heading ? '700' : '400', lineHeight: 18, color: heading ? '#111111' : '#222222'});
          y += heading ? 8 : 17;
        });
      }
      text(ctx, `${pageIndex + 1} / 2`, right, height - 70, {size: 14, weight: '700', align: 'right'});
    }));
    return pages;
  }

  function renderMonzo(data) {
    const page = createPage();
    drawInDesign(page, 840, (ctx, width, height) => {
      rect(ctx, 0, 0, width, height, {fill: '#ffffff'});
      const x = 60, right = 780, contentWidth = 720;
      text(ctx, 'monzo', x, 80, {size: 38, weight: '800', color: '#ff4b4b'});
      text(ctx, 'Personal Account statement', right, 80, {size: 24, weight: '700', align: 'right'});
      text(ctx, `${data.period_start || ''} - ${data.period_end || ''}`, right, 113, {size: 14, weight: '600', align: 'right'});

      text(ctx, data.customer_name, x, 170, {size: 14, weight: '700'});
      [data.address_unit, data.address_district, data.postal_code, data.country].filter(Boolean).forEach((value, index) => text(ctx, value, x, 194 + index * 20, {size: 13}));
      text(ctx, `Sort code: ${data.sort_code || ''}`, x, 300, {size: 13, weight: '600'});
      text(ctx, `Account number: ${data.account_number || ''}`, x, 322, {size: 13, weight: '600'});
      text(ctx, `BIC: ${data.bic || ''}`, x, 344, {size: 13, weight: '600'});
      fitText(ctx, `IBAN: ${data.iban || ''}`, x, 366, 330, {size: 13, weight: '600', minSize: 10});

      const rx = 500;
      const summaries = [
        [`${data.currency || '£'}${amount(data.opening_balance)}`, 'Personal Account balance'],
        [`${data.currency || '£'}${amount(data.balance_pots)}`, 'Balance in Pots'],
        [`${data.currency || '£'}${amount(data.total_outgoings)}`, 'Total outgoings'],
        [`+${data.currency || '£'}${amount(data.total_deposits)}`, 'Total deposits']
      ];
      summaries.forEach((row, index) => {
        const yy = 170 + index * 67;
        text(ctx, row[0], right, yy, {size: 16, weight: '700', align: 'right'});
        text(ctx, row[1], right, yy + 24, {size: 12, align: 'right'});
      });
      rule(ctx, x, 450, contentWidth, '#dddddd');
      const tableRows = parseTableRows(data.transactions);
      if (tableRows.length && /date/i.test(tableRows[0].cells[0] || '') && /type/i.test(tableRows[0].cells[1] || '')) tableRows.shift();
      let transactionEnd;
      if (tableRows.length) {
        transactionEnd = drawSimpleTable(ctx, tableRows.map(row => row.cells), x, 470, [78, 70, 190, 82, 82, 95, 123], {header: ['Date','Type','Description','Paid Out','Paid In','Balance','Ref'], headerFill: '#f5f5f5', headerHeight: 35, rowHeight: 38, size: 9, headerSize: 9, striped: true, alignments: ['left','left','left','right','right','right','left']});
      } else {
        transactionEnd = wrappedText(ctx, stripHtml(data.transactions) || 'There were no transactions during this period.', x, 478, contentWidth, {size: 13, lineHeight: 20});
      }
      // The legacy Monzo footer participates in normal document flow; it is
      // deliberately not pinned to the bottom of the A4 sheet.
      const footerY = Math.min(transactionEnd + 55, height - 125);
      rule(ctx, x, footerY, contentWidth, '#eeeeee');
      wrappedText(ctx, 'Monzo Bank Limited (https://monzo.com) is a company registered in England No. 9446231. Registered Office: Broadwalk House, 5 Appold Street, London EC2A 2AG. Monzo Bank Ltd is authorised by the Prudential Regulation Authority and regulated by the Financial Conduct Authority and the Prudential Regulation Authority. Our Financial Services Register number is 730427.', x, footerY + 20, contentWidth, {size: 10, lineHeight: 15, color: '#555555', maxLines: 5});
    });
    return [page];
  }

  async function renderSeaBank(data) {
    const logo = await loadImage('assets/logos/seabank-logo.png');
    const page = createPage('#eef2f5');
    drawInDesign(page, 890, (ctx, width, height) => {
      const pageX = 20;
      const pageY = 20;
      const contentX = 80;
      const contentRight = 810;
      const contentWidth = contentRight - contentX;
      rect(ctx, 0, 0, width, height, {fill: '#eef2f5'});
      rect(ctx, pageX, pageY, 850, height - 40, {fill: '#ffffff'});

      // Header geometry follows the legacy 850px statement page, including the
      // logo's intentional negative margin inside its 60px brand container.
      drawImage(ctx, logo, 40, 60, 260, 176);
      text(ctx, 'BANK STATEMENT', contentRight, 70, {size: 14, color: '#999999', align: 'right'});
      const meta = String(data.bill_number || '').replace(/<br\s*\/?\s*>/gi, '\n');
      wrappedText(ctx, `${meta}\n${data.issue_date || ''}`, contentRight, 92, 260, {size: 14, color: '#999999', align: 'right', lineHeight: 21, maxLines: 4});

      const infoY = 180;
      text(ctx, String(data.customer_name || '').toUpperCase(), contentX, infoY, {size: 20, weight: '700'});
      wrappedText(ctx, `SEABANK ACCOUNT: ${data.account_number || ''}\n${data.address_unit || ''}\n${data.address_street || ''}\n${data.address_district || ''}\n${data.country || 'PHILIPPINES'}`, contentX, infoY + 36, 375, {size: 12, lineHeight: 19.2, maxLines: 6});
      line(ctx, 465, infoY, 465, 315, '#eaeaea', 1);
      text(ctx, 'Contact Us', 490, infoY, {size: 12});
      wrappedText(ctx, 'Call 1500 130\n      0800 1500 130 toll-free\n      +6221 5086 7070 from overseas', 490, infoY + 31, 300, {size: 12, lineHeight: 19.2, maxLines: 4});
      text(ctx, 'Email cs@seabank.co.id', 490, infoY + 111, {size: 12});
      text(ctx, 'Find us on live chat in SeaBank app', 490, infoY + 142, {size: 12});

      function sectionHeader(title, y, subtitle) {
        text(ctx, title, (contentX + contentRight) / 2, y, {size: 18, align: 'center'});
        if (subtitle) text(ctx, subtitle, (contentX + contentRight) / 2, y + 31, {size: 12, color: '#999999', align: 'center'});
      }

      sectionHeader('ACCOUNT SUMMARY', 397, `${data.period_start || ''} to ${data.period_end || ''}`);
      const summaryY = 464;
      const summaryWidths = [110, 155, 155, 155, 155];
      rect(ctx, contentX, summaryY, contentWidth, 70, {fill: '#f7f7f7'});
      rect(ctx, contentX, summaryY, contentWidth, 167, {stroke: '#eaeaea'});
      const summaryHeaders = ['ACCOUNT', `STARTING BALANCE (${data.currency || 'PHP'})`, `TOTAL OUTGOING (${data.currency || 'PHP'})`, `TOTAL INCOMING (${data.currency || 'PHP'})`, `ENDING BALANCE (${data.currency || 'PHP'})`];
      let cx = contentX;
      summaryHeaders.forEach((label, index) => {
        const align = index ? 'right' : 'left';
        wrappedText(ctx, label, align === 'right' ? cx + summaryWidths[index] - 15 : cx + 15, summaryY + 14, summaryWidths[index] - 30, {size: 11, color: '#555555', lineHeight: 15, maxLines: 3, align});
        cx += summaryWidths[index];
      });
      rule(ctx, contentX, summaryY + 70, contentWidth, '#eaeaea');
      const summaryValues = ['SAVINGS', amount(data.opening_balance), amount(data.total_debits), amount(data.total_credits), amount(data.closing_balance)];
      cx = contentX;
      summaryValues.forEach((value, index) => {
        const align = index ? 'right' : 'left';
        text(ctx, value, align === 'right' ? cx + summaryWidths[index] - 15 : cx + 15, summaryY + 88, {size: 12, align});
        cx += summaryWidths[index];
      });
      rule(ctx, contentX, summaryY + 125, contentWidth, '#eaeaea');
      rect(ctx, contentX, summaryY + 125, contentWidth, 42, {fill: '#f4f4f4'});
      text(ctx, 'TOTAL: 0', contentRight - 15, summaryY + 139, {size: 12, align: 'right'});

      sectionHeader('SAVINGS - TRANSACTION DETAILS', 701);
      const transactionY = 743;
      const transactionWidths = [100, 360, 135, 135];
      rect(ctx, contentX, transactionY, contentWidth, 48, {fill: '#f7f7f7'});
      rect(ctx, contentX, transactionY, contentWidth, 48, {stroke: '#eaeaea'});
      const transactionHeaders = ['DATE', 'TRANSACTION', `OUTGOING (${data.currency || 'PHP'})`, `INCOMING (${data.currency || 'PHP'})`];
      cx = contentX;
      transactionHeaders.forEach((label, index) => {
        const align = index >= 2 ? 'right' : 'left';
        wrappedText(ctx, label, align === 'right' ? cx + transactionWidths[index] - 15 : cx + 15, transactionY + 14, transactionWidths[index] - 30, {size: 11, color: '#555555', lineHeight: 14, maxLines: 2, align});
        cx += transactionWidths[index];
      });

      const root = htmlRoot(data.transactions);
      const transactionRows = Array.from(root.querySelectorAll('tr')).map(row => {
        const cells = Array.from(row.querySelectorAll('td'));
        let subtitle = '';
        if (cells[1]) {
          const secondary = cells[1].querySelector('.col-gray');
          subtitle = secondary ? (secondary.textContent || '').trim() : '';
          if (secondary) secondary.remove();
        }
        return {cells: cells.map(cell => (cell.textContent || '').replace(/\s+/g, ' ').trim()), subtitle};
      }).filter(row => row.cells.length);
      let rowY = transactionY + 48;
      transactionRows.forEach(row => {
        const rowHeight = 58;
        rect(ctx, contentX, rowY, contentWidth, rowHeight, {stroke: '#eaeaea'});
        cx = contentX;
        row.cells.slice(0, 4).forEach((value, index) => {
          const align = index >= 2 ? 'right' : 'left';
          text(ctx, value, align === 'right' ? cx + transactionWidths[index] - 15 : cx + 15, rowY + 15, {size: 12, align});
          if (index === 1 && row.subtitle) text(ctx, row.subtitle, cx + 15, rowY + 34, {size: 11, color: '#888888'});
          cx += transactionWidths[index];
        });
        rowY += rowHeight;
      });
      sectionHeader('SAVINGS - INTEREST & TAX DETAILS', Math.min(rowY + 55, height - 105));
      text(ctx, 'page 1 of 1', contentRight, height - 60, {size: 13, color: '#999999', align: 'right'});
    });
    return [page];
  }

  function drawCmbStamp(ctx, centerX, centerY, bankName) {
    ctx.save();
    ctx.translate(centerX, centerY);
    ctx.rotate(-8 * Math.PI / 180);
    ctx.globalAlpha = 0.85;
    ctx.strokeStyle = '#E60012';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.ellipse(0, 0, 125, 72, 0, 0, Math.PI * 2);
    ctx.stroke();
    text(ctx, `${bankName || '招商银行'}股份有限公司信用卡`, 0, -48, {size: 17, weight: '700', color: '#E60012', align: 'center'});
    text(ctx, '中心', 0, -20, {size: 20, weight: '700', color: '#E60012', align: 'center'});
    text(ctx, '业务受理专用章', 0, 8, {size: 23, weight: '700', color: '#E60012', align: 'center'});
    text(ctx, '（电子）', 0, 42, {size: 19, weight: '700', color: '#E60012', align: 'center'});
    ctx.restore();
  }

  async function renderCmb(data) {
    const logo = await loadImage('assets/logos/cmb-logo.png');
    const page = createPage('#f7f9fb');
    drawInDesign(page, 896, (ctx, width, height) => {
      rect(ctx, 0, 0, width, height, {fill: '#f7f9fb'});
      const x = 48, right = 848, contentWidth = 800;
      drawImage(ctx, logo, x + 6, 29, 45, 45);
      text(ctx, data.bank_name || '招商银行', x + 70, 27, {size: 30, weight: '700', color: '#E60012'});
      text(ctx, data.bank_name_en || 'CHINA MERCHANTS BANK', x + 70, 64, {size: 11, weight: '600', color: '#E60012', family: FONT_MONO});
      line(ctx, 430, 28, 430, 68, '#E60012');
      text(ctx, '信用卡', 446, 29, {size: 26, weight: '700', color: '#E60012'});
      text(ctx, 'Credit Card', 446, 62, {size: 12, color: '#E60012', family: FONT_MONO, style: 'italic'});

      text(ctx, `招商银行信用卡对账单（个人消费卡账户  ${data.statement_period || ''}）（补）`, width / 2, 115, {size: 20, weight: '700', align: 'center'});
      text(ctx, `CMB Credit Card Statement (${data.period_end || ''})`, width / 2, 145, {size: 22, align: 'center', family: FONT_MONO});
      const address = [data.postal_code, data.address_district, data.address_street, data.address_unit, data.customer_name].filter(Boolean);
      address.forEach((value, index) => text(ctx, value, x, 195 + index * 37, {size: 17, weight: '700', family: index === 0 ? FONT_MONO : FONT_SANS}));
      rule(ctx, x, 390, 200, '#9a9a9a', 1.5);
      rule(ctx, x + 200, 398, 600, '#9a9a9a', 1.5);

      const left = [
        ['账单日', 'Statement Date', data.issue_date],
        ['到期还款日', 'Payment Due Date', data.payment_due_date],
        ['本期应还金额', 'New Balance', `￥${amount(data.total_due)}`],
        ['本期最低还款额', 'Min. Payment', `￥${amount(data.min_payment)}`]
      ];
      left.forEach((row, index) => {
        const yy = 430 + index * 67;
        text(ctx, row[0], x, yy, {size: 19, weight: '700'});
        text(ctx, row[1], x, yy + 27, {size: 15, family: FONT_MONO});
        text(ctx, row[2], 430, yy + 10, {size: 18, weight: '700', align: 'right', family: FONT_MONO});
      });
      text(ctx, '信用额度', 500, 430, {size: 19, weight: '700'});
      text(ctx, 'Credit Limit', 500, 457, {size: 15, family: FONT_MONO});
      text(ctx, `￥${amount(data.credit_limit)}`, right - 24, 440, {size: 18, weight: '700', align: 'right', family: FONT_MONO});

      rule(ctx, x, 710, contentWidth, '#9a9a9a', 1.3);
      text(ctx, '本期账务明细  Transaction Details', x, 730, {size: 21, weight: '700'});
      text(ctx, '人民币账户  RMB A/C', x, 764, {size: 13});
      const rawRows = parseTableRows(data.transactions);
      const rows = rawRows.map(row => row.cells.slice(0, 6));
      let y = drawSimpleTable(ctx, rows, x, 790, [105, 105, 250, 115, 100, 125], {header: ['交易日\nTrans Date','记账日\nPost Date','交易摘要\nDescription','人民币金额\nRMB Amount','卡号末四位\nCard Number','交易地金额\nOriginal Amount'], headerFill: '#d9d9d9', headerHeight: 60, rowHeight: 35, size: 10, headerSize: 10, striped: true, alignments: ['left','left','left','right','center','right']});
      y += 18;
      const formula = [
        ['本期应还金额', amount(data.total_due)], ['上期账单金额', amount(data.previous_balance)], ['上期还款金额', amount(data.new_payments)], ['本期账单金额', amount(data.new_charges)], ['本期调整金额', amount(data.adjustment)], ['循环利息', amount(data.interest_charge)]
      ];
      const gap = 15, boxWidth = (contentWidth - gap * 5) / 6;
      formula.forEach((item, index) => {
        const bx = x + index * (boxWidth + gap);
        rect(ctx, bx, y, boxWidth, 72, {stroke: '#111111'});
        fitText(ctx, item[0], bx + boxWidth / 2, y + 9, boxWidth - 8, {size: 11, weight: '700', align: 'center', minSize: 8});
        text(ctx, `￥${item[1]}`, bx + boxWidth / 2, y + 42, {size: 11, align: 'center', family: FONT_MONO});
      });
      const noteY = Math.min(y + 92, height - 250);
      wrappedText(ctx, '(1)上述交易摘要中的商户名称仅供您参考，如与签购单不符，请以签购单为准。\n(2)若“本期应还金额”为负数，表示账户中尚有溢缴款，本期账单仅供对账参考。\n(3)通过本行系统缴款，您的信用额度一般可于缴款后立即恢复。', x, noteY, 500, {size: 11, lineHeight: 18, maxLines: 6});
      drawCmbStamp(ctx, 700, height - 125, data.bank_name);
      text(ctx, '[END]', x, height - 70, {size: 13, family: FONT_MONO});
    });
    return [page];
  }

  function renderOctopus(data) {
    const ink = '#050505';
    const paddingX = 75.6;
    const right = PAGE.width - paddingX;
    const contentWidth = right - paddingX;

    function drawOctopusLogo(ctx) {
      ctx.save();
      font(ctx, 32, '800');
      const octopusWidth = ctx.measureText('octopus').width;
      font(ctx, 32, '300');
      const energyWidth = ctx.measureText('energy').width;
      const startX = PAGE.width / 2 - (octopusWidth + energyWidth) / 2;
      text(ctx, 'octopus', startX, 94.5, {size: 32, weight: '800'});
      text(ctx, 'energy', startX + octopusWidth, 94.5, {size: 32, weight: '300'});
      ctx.restore();
    }

    function drawFooter(ctx) {
      const y = 1002;
      const colWidth = contentWidth / 3;
      text(ctx, 'Octopus Energy Limited', paddingX, y, {size: 10, weight: '700'});
      text(ctx, 'W  octopus.energy', paddingX, y + 18, {size: 9});
      text(ctx, 'E  hello@octopus.energy', paddingX, y + 32, {size: 9});
      text(ctx, 'P  0808 164 1088', paddingX, y + 46, {size: 9});

      text(ctx, 'Registered Office', paddingX + colWidth, y, {size: 10, weight: '700'});
      wrappedText(ctx, 'UK House, 5th floor, 164-182 Oxford Street,\nLondon, W1D 1NN', paddingX + colWidth, y + 18, colWidth - 12, {size: 9, lineHeight: 14, maxLines: 3});

      wrappedText(ctx, 'Registered in England & Wales No. 09263424\nVAT Number: 358672751', paddingX + colWidth * 2, y, colWidth, {size: 9, lineHeight: 14, maxLines: 3});
    }

    function drawBalanceBox(ctx, x, y, width, dateLabel, caption, value) {
      rect(ctx, x, y, width, 42, {fill: ink});
      text(ctx, `On ${dateLabel}`, x + 12, y + 8, {size: 12, color: '#ffffff'});
      text(ctx, caption, x + 12, y + 24, {size: 12, weight: '700', color: '#ffffff'});
      fitText(ctx, `${data.currency || '£'}${value || '0.00'}`, x + width - 12, y + 14, width * 0.38, {size: 14, weight: '700', color: '#ffffff', align: 'right', minSize: 10});
    }

    function drawAccountInfo(ctx, x, y) {
      text(ctx, `Your Account Number: ${data.account_number || ''}`, x, y, {size: 11, weight: '700'});
      fitText(ctx, `Bill Reference: ${data.bill_number || ''} (${data.issue_date || ''})`, x, y + 18, 238, {size: 11, weight: '700', minSize: 8});
    }

    function drawBarcode(ctx, x, y, width, height) {
      const seed = `982602200${data.account_number || ''}`;
      rect(ctx, x, y, width, height, {fill: '#ffffff'});
      let cursor = x + 3;
      for (let index = 0; cursor < x + width - 3; index += 1) {
        const code = seed.charCodeAt(index % seed.length) || 49;
        const barWidth = 1 + ((code + index) % 3);
        if ((code + index) % 4 !== 0) rect(ctx, cursor, y, barWidth, height, {fill: '#000000'});
        cursor += barWidth + 1 + ((code >> 2) % 2);
      }
      fitText(ctx, seed, x + width / 2, y + height + 5, width, {size: 10, family: FONT_MONO, align: 'center', minSize: 8});
    }

    const first = createPage();
    const ctx = first.ctx;
    drawOctopusLogo(ctx);

    const leftX = paddingX;
    const leftWidth = 370;
    const rightX = leftX + leftWidth + 40;
    const rightWidth = right - rightX;
    let y = 174;
    [data.customer_name, data.address_unit, data.address_street, data.address_district].filter(Boolean).forEach((value, index) => {
      text(ctx, value, leftX, y + index * 18, {size: 13, weight: index === 0 ? '700' : '400'});
    });
    y += 100;
    text(ctx, 'Your energy account', leftX, y, {size: 32, weight: '800'});
    text(ctx, `${data.period_start || ''} - ${data.period_end || ''}`, leftX, y + 47, {size: 16});
    y += 94;
    drawBalanceBox(ctx, leftX, y, leftWidth, data.period_start || '', 'your previous balance was', data.previous_balance);
    y += 67;
    rule(ctx, leftX, y, leftWidth, ink, 1);
    y += 22;
    text(ctx, '1. We have credited you', leftX, y, {size: 16, weight: '700'});
    y += 29;
    const creditRows = [
      [`Reversed electricity charge (${data.t1_date || ''})`, data.c1],
      [`Reversed electricity charge (${data.t2_date || ''})`, data.c2],
      [`Reversed electricity charge (${data.t3_date || ''})`, data.c3]
    ];
    creditRows.forEach(row => {
      fitText(ctx, row[0], leftX, y, 205, {size: 11, minSize: 8});
      text(ctx, data.issue_date || '', leftX + 270, y, {size: 11, align: 'right'});
      text(ctx, `+ ${data.currency || '£'}${amount(row[1])}`, leftX + leftWidth, y, {size: 11, align: 'right'});
      y += 29;
    });
    rule(ctx, leftX, y + 2, leftWidth, ink, 1);
    y += 27;
    drawBalanceBox(ctx, leftX, y, leftWidth, data.period_end || '', 'your new balance is', data.total_due);
    y += 64;
    wrappedText(ctx, 'As you have no Direct Debit in place, your balance is due for payment in 14 days. There are 5 ways you can pay, as detailed in this bill.', leftX, y, leftWidth, {size: 11.5, lineHeight: 18, maxLines: 5});

    drawAccountInfo(ctx, rightX, 174);
    text(ctx, 'Could you pay less?', rightX, 253, {size: 12, weight: '700'});
    wrappedText(ctx, 'Remember - it might be worth thinking about switching your tariff or supplier.', rightX, 275, rightWidth, {size: 11, lineHeight: 17, maxLines: 4});
    text(ctx, 'Emergency numbers', rightX, 363, {size: 12, weight: '700'});
    wrappedText(ctx, 'Smell gas? Call 0800 111 999\nPower cut? Call 105 to get help\nYour Electricity Distributor is: Southern Electric Power Distribution (105)', rightX, 385, rightWidth, {size: 11, lineHeight: 18, maxLines: 8});
    drawFooter(ctx);

    const second = createPage();
    const ctx2 = second.ctx;
    drawOctopusLogo(ctx2);
    const gap = 30;
    const columnWidth = (contentWidth - gap) / 2;
    const secondX = paddingX + columnWidth + gap;
    y = 174;
    text(ctx2, 'Contacting us', paddingX, y, {size: 16, weight: '700'});
    wrappedText(ctx2, 'Contact us by email and get a response within hours. Of course, if you need to you can also get a hold of us on the phone, or even by post.', paddingX, y + 30, columnWidth, {size: 11.5, lineHeight: 18, maxLines: 6});
    text(ctx2, 'Email: hello@octopus.energy', paddingX, y + 130, {size: 11.5, weight: '700'});
    text(ctx2, 'Phone: 0808 164 1088', paddingX, y + 154, {size: 11.5, weight: '700'});
    wrappedText(ctx2, 'Trading office: UK House, 5th floor, 164-182 Oxford Street, London, W1D 1NN', paddingX, y + 178, columnWidth, {size: 11.5, weight: '700', lineHeight: 18, maxLines: 3});
    wrappedText(ctx2, "Please don't hesitate to contact us if you've any questions, comments, or complaints.", paddingX, y + 242, columnWidth, {size: 11.5, lineHeight: 18, maxLines: 3});
    text(ctx2, 'How much did you use?', paddingX, y + 318, {size: 16, weight: '700'});
    wrappedText(ctx2, 'Please visit our website for advice on how to save energy in your home.', paddingX, y + 348, columnWidth, {size: 11.5, lineHeight: 18, maxLines: 3});

    drawAccountInfo(ctx2, secondX, y);
    text(ctx2, 'Advice and complaints', secondX, y + 73, {size: 16, weight: '700'});
    const advice = "Contact Citizens Advice if you need help with an energy problem - for example with your bills or meters, or if you're struggling to pay for the energy you use. They're the official source of free and independent energy advice and support.\n\nGo to: citizensadvice.org.uk/energy or call their consumer service on 0808 223 1133 Mon to Fri, 9am-5pm.\n\nOr, if you live in Scotland, you can contact energyadvice.scot for independent help.\n\nGo to: energyadvice.scot/email-us, or call their customer service on 0808 196 8660 Monday to Friday, 9am to 5pm.\n\nIf you feel that our service has not met your expectations, please get in touch so we can put things right.\n\nFirst: Contact our team.\nThen: If an advisor is not able to resolve your query, you can ask for it to be escalated to a specialist or team leader as appropriate.\nFinally: If you're still not happy with our decision, you can contact our Operations Manager for an independent review, and you will receive a reply within 5 working days.\n\nIf you have followed the above steps, but your complaint remains unresolved after 8 weeks you can contact the Energy Ombudsman on 0330 440 1624 or at www.energyombudsman.org. This is a free and independent service whose decisions we must abide by.";
    wrappedText(ctx2, advice, secondX, y + 104, columnWidth, {size: 8.8, lineHeight: 11.5, maxLines: 35});

    const paymentY = 680;
    const paymentHeight = 300;
    rect(ctx2, paddingX, paymentY, contentWidth, paymentHeight, {stroke: ink});
    rule(ctx2, paddingX, paymentY + 42, contentWidth, ink, 1);
    text(ctx2, 'Your payment options', paddingX + 15, paymentY + 13, {size: 13, weight: '700'});
    text(ctx2, 'You can read our complaints policy on our website.', right - 15, paymentY + 13, {size: 11, align: 'right'});
    const paymentColWidth = (contentWidth - 50) / 2;
    const paymentLeft = paddingX + 15;
    const paymentRight = paymentLeft + paymentColWidth + 20;
    text(ctx2, 'Direct Debit', paymentLeft, paymentY + 59, {size: 11, weight: '700'});
    wrappedText(ctx2, "It's easy to set up a monthly Direct Debit to keep on top of your energy payments. Simply log on to your online account at www.octopus.energy.", paymentLeft, paymentY + 78, paymentColWidth, {size: 10, lineHeight: 14, maxLines: 5});
    text(ctx2, 'Bank transfer', paymentLeft, paymentY + 151, {size: 11, weight: '700'});
    wrappedText(ctx2, `Pay us directly from your bank account. Make sure to enter your account number (${data.account_number || ''}) as the payment reference. Our bank details - Account number: 71981658 & Sort Code: 58-16-52.`, paymentLeft, paymentY + 170, paymentColWidth, {size: 9.5, lineHeight: 13, maxLines: 6});
    text(ctx2, 'Cheque', paymentLeft, paymentY + 242, {size: 11, weight: '700'});
    wrappedText(ctx2, `Write your account number (${data.account_number || ''}) on the back, make your cheque payable to "Octopus Energy Ltd", and post it to: Octopus Energy, UK House, 5th floor, 164-182 Oxford Street, London, W1D 1NN.`, paymentLeft, paymentY + 260, paymentColWidth, {size: 7.7, lineHeight: 9.5, maxLines: 4});

    text(ctx2, 'Credit or Debit Card', paymentRight, paymentY + 59, {size: 11, weight: '700'});
    wrappedText(ctx2, 'Visit us online at www.octopus.energy/payment to make a payment by card. Alternatively you can pay by debit card at your local PayPoint with the barcode below.', paymentRight, paymentY + 78, paymentColWidth, {size: 10, lineHeight: 14, maxLines: 5});
    text(ctx2, 'Cash', paymentRight, paymentY + 151, {size: 11, weight: '700'});
    wrappedText(ctx2, 'Simply take this barcode to your local PayPoint to pay by cash. It links to your account so whatever you pay will be transferred to your account.', paymentRight, paymentY + 170, paymentColWidth, {size: 10, lineHeight: 14, maxLines: 4});
    drawBarcode(ctx2, paymentRight, paymentY + 226, paymentColWidth, 30);
    drawFooter(ctx2);
    return [first, second];
  }

  const SUPPORTED_CODES = Object.freeze(new Set([
    'de-monese',
    'de-wise',
    'gb-wise',
    'gb-wisegbpstatementuk',
    'gb-octopusenergybill',
    'gb-kraken',
    'gb-monzo',
    'ph-seabank',
    'cn-cmb-credit'
  ]));

  function normalizeCode(code) {
    return String(code || '').trim().toLowerCase();
  }

  function supports(code) {
    return SUPPORTED_CODES.has(normalizeCode(code));
  }

  async function render(code, input) {
    if (document.fonts && document.fonts.ready) {
      try { await document.fonts.ready; } catch (_) { /* fallback font */ }
    }
    const normalizedCode = normalizeCode(code);
    const data = Object.freeze(cloneData(input));
    let pages;
    switch (normalizedCode) {
      case 'de-monese': pages = renderMonese(data); break;
      case 'gb-wisegbpstatementuk': pages = await renderWise(data, 'uk'); break;
      case 'gb-wise': pages = await renderWise(data, 'uk'); break;
      case 'de-wise': pages = await renderWise(data, 'de'); break;
      case 'gb-octopusenergybill': pages = renderOctopus(data); break;
      case 'gb-kraken': pages = await renderKraken(data); break;
      case 'gb-monzo': pages = renderMonzo(data); break;
      case 'ph-seabank': pages = await renderSeaBank(data); break;
      case 'cn-cmb-credit': pages = await renderCmb(data); break;
      default:
        throw new Error(`V2 尚未实现账单类型：${normalizedCode || '(empty)'}`);
    }
    return pages.map(page => ({canvas: page.canvas}));
  }

  async function sha256(canvas) {
    const blob = await new Promise((resolve, reject) => canvas.toBlob(value => value ? resolve(value) : reject(new Error('Canvas 编码失败')), 'image/png'));
    const digest = await crypto.subtle.digest('SHA-256', await blob.arrayBuffer());
    return Array.from(new Uint8Array(digest)).map(byte => byte.toString(16).padStart(2, '0')).join('');
  }

  window.DocumentCanvasV2 = Object.freeze({PAGE, render, supports, sha256});
})();
