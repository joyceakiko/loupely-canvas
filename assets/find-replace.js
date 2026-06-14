// Loupely Canvas - Find & Replace for HTML boxes
//
// The browser's native Ctrl+F only searches rendered page text, never the
// value of a textarea, so it can never find anything inside the code boxes
// this theme uses. This script intercepts Ctrl+F (Cmd+F on Mac) when the
// cursor is inside one of those boxes and opens a find and replace bar that
// searches the actual contents. Outside those boxes the browser's normal
// find is left untouched.
//
// Works in: Custom HTML blocks, the block editor code view, the classic
// editor code box, and this theme's header, footer, head, body and per page
// override boxes. Loads only in the admin, never on the public site.
//
// Supports case sensitivity, whole word, and regular expressions.

(function () {
  "use strict";

  var bar = null;
  var findInput = null;
  var replaceInput = null;
  var counterEl = null;
  var liveEl = null;
  var caseBtn = null;
  var wordBtn = null;
  var regexBtn = null;

  var caseSensitive = false;
  var wholeWord = false;
  var useRegex = false;

  var activeTextarea = null;
  var matches = [];   // array of { start, end }
  var current = -1;

  var attachedDocs = [];

  // ---- target detection ------------------------------------------------

  function isTarget(el) {
    if (!el || el.tagName !== "TEXTAREA") return false;
    var cl = el.classList;
    if (cl) {
      if (cl.contains("lc-html-field")) return true;                          // theme boxes
      if (cl.contains("block-editor-plain-text")) return true;                 // Custom HTML block
      if (cl.contains("block-editor-block-list__block-html-textarea")) return true; // Edit as HTML
      if (cl.contains("editor-post-text-editor")) return true;                 // block editor code view
      if (cl.contains("wp-editor-area")) return true;                          // classic editor
    }
    if (el.closest && el.closest(".wp-block-html")) return true;
    return false;
  }

  // ---- bar construction ------------------------------------------------

  function injectStyles() {
    if (document.getElementById("lc-fr-styles")) return;
    var css =
      "#lc-fr-bar{position:fixed;top:64px;right:24px;z-index:999999;display:flex;align-items:center;gap:6px;" +
      "background:#1e1e1e;color:#fff;padding:8px 10px;border-radius:8px;box-shadow:0 8px 28px rgba(0,0,0,0.35);" +
      "font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:13px;}" +
      "#lc-fr-bar input{background:#2b2b2b;border:1px solid #3a3a3a;color:#fff;border-radius:5px;padding:5px 8px;" +
      "font-size:13px;width:150px;font-family:Menlo,Consolas,monospace;}" +
      "#lc-fr-bar input:focus{outline:2px solid #4A7FA8;outline-offset:0;border-color:#4A7FA8;}" +
      "#lc-fr-bar button{background:#2b2b2b;border:1px solid #3a3a3a;color:#fff;border-radius:5px;cursor:pointer;" +
      "padding:5px 9px;font-size:12px;line-height:1;font-family:inherit;}" +
      "#lc-fr-bar button:hover{background:#3a3a3a;}" +
      "#lc-fr-bar button:focus-visible{outline:2px solid #4A7FA8;outline-offset:1px;}" +
      "#lc-fr-bar button.lc-fr-on{background:#4A7FA8;border-color:#4A7FA8;}" +
      "#lc-fr-counter{min-width:60px;text-align:center;opacity:0.75;font-variant-numeric:tabular-nums;}" +
      "#lc-fr-counter.lc-fr-error{color:#ff8a80;opacity:1;}" +
      "#lc-fr-close{font-size:15px;padding:4px 8px;}" +
      ".lc-fr-sr{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;border:0;}";
    var style = document.createElement("style");
    style.id = "lc-fr-styles";
    style.textContent = css;
    document.head.appendChild(style);
  }

  function mkButton(label, title, ariaPressed) {
    var b = document.createElement("button");
    b.type = "button";
    b.textContent = label;
    b.title = title;
    b.setAttribute("aria-label", title);
    if (ariaPressed) b.setAttribute("aria-pressed", "false");
    return b;
  }

  function buildBar() {
    if (bar) return;
    injectStyles();

    bar = document.createElement("div");
    bar.id = "lc-fr-bar";
    bar.setAttribute("role", "search");
    bar.setAttribute("aria-label", "Find and replace in code box");
    bar.style.display = "none";

    findInput = document.createElement("input");
    findInput.type = "text";
    findInput.placeholder = "Find in this box";
    findInput.setAttribute("aria-label", "Find");

    replaceInput = document.createElement("input");
    replaceInput.type = "text";
    replaceInput.placeholder = "Replace with";
    replaceInput.setAttribute("aria-label", "Replace with");

    counterEl = document.createElement("span");
    counterEl.id = "lc-fr-counter";
    counterEl.textContent = "0/0";

    liveEl = document.createElement("span");
    liveEl.className = "lc-fr-sr";
    liveEl.setAttribute("aria-live", "polite");

    caseBtn = mkButton("Aa", "Match case", true);
    wordBtn = mkButton("W", "Whole word", true);
    regexBtn = mkButton(".*", "Regular expression", true);

    var prevBtn = mkButton("\u2191", "Previous match (Shift+Enter)", false);
    var nextBtn = mkButton("\u2193", "Next match (Enter)", false);
    var replaceBtn = mkButton("Replace", "Replace this match", false);
    var replaceAllBtn = mkButton("All", "Replace all matches", false);
    var closeBtn = mkButton("\u00d7", "Close (Esc)", false);
    closeBtn.id = "lc-fr-close";

    bar.appendChild(findInput);
    bar.appendChild(counterEl);
    bar.appendChild(caseBtn);
    bar.appendChild(wordBtn);
    bar.appendChild(regexBtn);
    bar.appendChild(prevBtn);
    bar.appendChild(nextBtn);
    bar.appendChild(replaceInput);
    bar.appendChild(replaceBtn);
    bar.appendChild(replaceAllBtn);
    bar.appendChild(closeBtn);
    bar.appendChild(liveEl);
    document.body.appendChild(bar);

    findInput.addEventListener("input", function () { recompute(true, true); });
    findInput.addEventListener("keydown", function (e) {
      if (e.key === "Enter") { e.preventDefault(); navigate(e.shiftKey ? -1 : 1); }
      else if (e.key === "Escape") { e.preventDefault(); closeBar(); }
    });
    replaceInput.addEventListener("keydown", function (e) {
      if (e.key === "Escape") { e.preventDefault(); closeBar(); }
    });

    caseBtn.addEventListener("click", function () { toggle(caseBtn, "case"); });
    wordBtn.addEventListener("click", function () { toggle(wordBtn, "word"); });
    regexBtn.addEventListener("click", function () { toggle(regexBtn, "regex"); });

    prevBtn.addEventListener("click", function () { navigate(-1); });
    nextBtn.addEventListener("click", function () { navigate(1); });
    replaceBtn.addEventListener("click", replaceCurrent);
    replaceAllBtn.addEventListener("click", replaceAll);
    closeBtn.addEventListener("click", closeBar);
  }

  function toggle(btn, which) {
    if (which === "case") caseSensitive = !caseSensitive;
    if (which === "word") wholeWord = !wholeWord;
    if (which === "regex") useRegex = !useRegex;
    var on = (which === "case") ? caseSensitive : (which === "word") ? wholeWord : useRegex;
    btn.classList.toggle("lc-fr-on", on);
    btn.setAttribute("aria-pressed", on ? "true" : "false");
    recompute(true, true);
  }

  // ---- open / close ----------------------------------------------------

  function openBar(textarea) {
    buildBar();
    activeTextarea = textarea;
    bar.style.display = "flex";

    var sel = "";
    try {
      var s = textarea.selectionStart, e = textarea.selectionEnd;
      if (typeof s === "number" && e > s) sel = textarea.value.substring(s, e);
    } catch (err) {}
    if (sel && sel.indexOf("\n") === -1 && !useRegex) findInput.value = sel;

    recompute(true, true);
    findInput.focus();
    findInput.select();
  }

  function closeBar() {
    if (bar) bar.style.display = "none";
    matches = [];
    current = -1;
    if (activeTextarea) {
      try { activeTextarea.focus(); } catch (err) {}
    }
  }

  // ---- search ----------------------------------------------------------

  function escapeRegExp(s) {
    return s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
  }

  // Returns a RegExp for the current term and options, null if the term is
  // empty, or false if the regex is invalid.
  function buildRegex() {
    var term = findInput.value;
    if (term === "") return null;
    var pattern;
    if (useRegex) {
      pattern = term;
    } else {
      pattern = escapeRegExp(term);
      if (wholeWord) pattern = "\\b" + pattern + "\\b";
    }
    var flags = "g" + (caseSensitive ? "" : "i");
    try {
      return new RegExp(pattern, flags);
    } catch (err) {
      return false;
    }
  }

  function recompute(resetIndex, keepInInput) {
    matches = [];
    var re = buildRegex();

    if (re === false) {
      counterEl.textContent = "regex?";
      counterEl.classList.add("lc-fr-error");
      announce("Invalid regular expression");
      return;
    }
    counterEl.classList.remove("lc-fr-error");

    if (!activeTextarea || re === null) {
      updateCounter();
      return;
    }

    var value = activeTextarea.value;
    var m;
    var guard = 0;
    while ((m = re.exec(value)) !== null) {
      matches.push({ start: m.index, end: m.index + m[0].length });
      if (m.index === re.lastIndex) re.lastIndex++;
      if (++guard > 100000) break;
    }

    if (resetIndex) current = matches.length ? 0 : -1;
    if (current >= matches.length) current = matches.length - 1;
    if (matches.length && current < 0) current = 0;

    updateCounter();
    if (current >= 0) reveal(current, keepInInput !== false);
  }

  function navigate(delta) {
    if (!matches.length) { recompute(true, false); if (!matches.length) return; }
    current = (current + delta + matches.length) % matches.length;
    updateCounter();
    reveal(current, false);
  }

  function reveal(idx, keepInInput) {
    if (!activeTextarea || idx < 0 || idx >= matches.length) return;
    var start = matches[idx].start;
    var end = matches[idx].end;
    try {
      activeTextarea.focus();
      activeTextarea.setSelectionRange(start, end);
      scrollToOffset(activeTextarea, start);
    } catch (err) {}
    if (keepInInput) findInput.focus();
  }

  function updateCounter() {
    if (!counterEl) return;
    if (!matches.length) {
      counterEl.textContent = "0/0";
      announce(findInput.value ? "No matches" : "");
    } else {
      counterEl.textContent = (current + 1) + "/" + matches.length;
      announce((current + 1) + " of " + matches.length + " matches");
    }
  }

  function announce(msg) {
    if (liveEl) liveEl.textContent = msg;
  }

  // ---- scroll the box to a character offset ----------------------------

  function scrollToOffset(textarea, index) {
    var doc = textarea.ownerDocument;
    var view = doc.defaultView || window;
    var cs = view.getComputedStyle(textarea);
    var mirror = doc.createElement("div");
    var props = [
      "fontFamily", "fontSize", "fontWeight", "fontStyle", "lineHeight",
      "letterSpacing", "textTransform", "tabSize",
      "paddingTop", "paddingRight", "paddingBottom", "paddingLeft",
      "borderTopWidth", "borderRightWidth", "borderBottomWidth", "borderLeftWidth"
    ];
    for (var i = 0; i < props.length; i++) mirror.style[props[i]] = cs[props[i]];
    mirror.style.position = "absolute";
    mirror.style.top = "-99999px";
    mirror.style.left = "-99999px";
    mirror.style.visibility = "hidden";
    mirror.style.height = "auto";
    mirror.style.width = textarea.clientWidth + "px";
    mirror.style.boxSizing = "border-box";
    mirror.style.whiteSpace = "pre-wrap";
    mirror.style.overflowWrap = "break-word";
    mirror.style.wordWrap = "break-word";

    mirror.textContent = textarea.value.substring(0, index);
    var marker = doc.createElement("span");
    marker.textContent = "\u200b";
    mirror.appendChild(marker);
    doc.body.appendChild(mirror);
    var markerTop = marker.offsetTop;
    doc.body.removeChild(mirror);

    var target = markerTop - (textarea.clientHeight / 2);
    textarea.scrollTop = target > 0 ? target : 0;
  }

  // ---- write back into the box (React aware) ---------------------------

  function setValue(textarea, value, caretAt) {
    var view = textarea.ownerDocument.defaultView || window;
    var setter = Object.getOwnPropertyDescriptor(view.HTMLTextAreaElement.prototype, "value").set;
    setter.call(textarea, value);
    textarea.dispatchEvent(new view.Event("input", { bubbles: true }));
    if (typeof caretAt === "number") {
      try { textarea.setSelectionRange(caretAt, caretAt); } catch (err) {}
    }
  }

  // Build the replacement for one match, honoring regex backreferences when
  // regex mode is on and treating the text literally otherwise.
  function buildReplacement(matchStr) {
    var rep = replaceInput.value;
    if (!useRegex) return rep;
    var re = buildRegex();
    if (!re || re === false) return rep;
    return matchStr.replace(new RegExp(re.source, re.flags.replace("g", "")), rep);
  }

  function replaceCurrent() {
    if (!activeTextarea || current < 0 || !matches.length) return;
    var start = matches[current].start;
    var end = matches[current].end;
    var value = activeTextarea.value;
    var matchStr = value.slice(start, end);
    var rep = buildReplacement(matchStr);
    var next = value.slice(0, start) + rep + value.slice(end);
    setValue(activeTextarea, next, start + rep.length);

    recompute(false, true);
    if (matches.length) {
      var target = 0;
      for (var k = 0; k < matches.length; k++) {
        if (matches[k].start >= start) { target = k; break; }
      }
      current = target;
      updateCounter();
      reveal(current, true);
    } else {
      updateCounter();
    }
  }

  function replaceAll() {
    if (!activeTextarea) return;
    var re = buildRegex();
    if (!re || re === false) return;
    var rep = replaceInput.value;
    var value = activeTextarea.value;
    var n = matches.length;
    var next;
    if (useRegex) {
      next = value.replace(re, rep);
    } else {
      var safe = rep.replace(/\$/g, "$$$$"); // protect dollar signs in literal mode
      next = value.replace(re, safe);
    }
    if (next !== value) {
      setValue(activeTextarea, next, 0);
      announce("Replaced " + n + " matches");
    }
    recompute(true, true);
  }

  // ---- key interception ------------------------------------------------

  function onKeydown(doc, e) {
    var meta = e.ctrlKey || e.metaKey;
    var key = e.key;
    var barOpen = bar && bar.style.display !== "none";

    if (meta && (key === "f" || key === "F")) {
      var el = doc.activeElement;
      if (isTarget(el)) {
        e.preventDefault();
        e.stopPropagation();
        if (barOpen && activeTextarea === el) {
          findInput.focus();
          findInput.select();
        } else {
          openBar(el);
        }
      }
      return;
    }

    if (!barOpen) return;

    var inBox = doc.activeElement === activeTextarea;

    if (key === "Escape" && inBox) {
      e.preventDefault();
      closeBar();
      return;
    }

    // While the bar is open, Enter inside the code box jumps to the next
    // match instead of inserting a newline. Press Esc first to type a newline.
    if (key === "Enter" && inBox) {
      e.preventDefault();
      navigate(e.shiftKey ? -1 : 1);
      return;
    }

    // F3 or Cmd/Ctrl+G repeats the find from anywhere.
    if (key === "F3" || (meta && (key === "g" || key === "G"))) {
      e.preventDefault();
      navigate(e.shiftKey ? -1 : 1);
      return;
    }
  }

  function attachTo(doc) {
    if (!doc || attachedDocs.indexOf(doc) !== -1) return;
    try {
      doc.addEventListener("keydown", function (e) { onKeydown(doc, e); }, true);
      attachedDocs.push(doc);
    } catch (err) {}
  }

  attachTo(document);

  // The block editor canvas is often inside an iframe. Poll for it and any
  // other same origin frames, and attach once each.
  setInterval(function () {
    var frames = document.querySelectorAll("iframe");
    for (var i = 0; i < frames.length; i++) {
      try {
        var d = frames[i].contentDocument;
        if (d) attachTo(d);
      } catch (err) {
        // Cross origin frame, skip.
      }
    }
  }, 800);

})();
