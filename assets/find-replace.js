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
//
// Matches are shown with a highlight layer painted over the box, not the
// textarea's own text selection, so they stay visible no matter where the
// cursor is and a stray keystroke can never replace a highlighted match.
// Replacements use the right undo mechanism for the box. In plain textareas
// (this theme's boxes and the classic editor) they go through the browser's
// native text-insertion command, so Ctrl+Z / Cmd+Z and redo work normally. In
// the block editor, where the editor's history does not reliably record edits
// made from outside, the tool keeps its own undo/redo of the replacements it
// made and handles those keys itself while a find session is active.

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

  // The highlight layer painted over the active box. One at a time.
  var overlay = null;

  // The tool's own undo/redo of replacements, used in the block editor where
  // the editor's history does not reliably record programmatic edits. Each
  // entry is { textarea, before, after }. selfEditing marks writes we make
  // ourselves so they are not mistaken for the user editing.
  var editUndo = [];
  var editRedo = [];
  var selfEditing = false;

  // On Mac, Cmd+G is the natural "find next" shortcut; elsewhere it is F3.
  // Both are wired up below, but the hint shows the one people expect.
  var isMac = /Mac|iPhone|iPad|iPod/.test(navigator.platform || navigator.userAgent || "");
  var repeatHint = (isMac ? "\u2318G" : "F3") + " for next match in editor";
  var repeatTitle = isMac
    ? "Find next from inside the editor: Cmd+G (Shift+Cmd+G for previous)"
    : "Find next from inside the editor: F3 (Shift+F3 for previous)";

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

  // True only for plain textareas where the browser's native undo is the right
  // mechanism: this theme's own boxes and the classic editor. Everywhere else
  // (the block editor) React owns the field and its own undo history, so we
  // must update through the value setter, never the native insert command.
  function usesNativeUndo(el) {
    if (!el || !el.classList) return false;
    if (el.classList.contains("lc-html-field")) return true;
    if (el.classList.contains("wp-editor-area")) return true;
    return false;
  }

  // ---- bar construction ------------------------------------------------

  function injectStyles() {
    if (document.getElementById("lc-fr-styles")) return;
    var css =
      "#lc-fr-bar{position:fixed;top:64px;right:24px;z-index:999999;display:flex;align-items:center;gap:6px;" +
      "background:#fff;color:#1a2420;padding:8px 10px;border-radius:8px;border:1px solid #d5ded6;box-shadow:0 8px 28px rgba(26,36,32,0.18);" +
      "font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;font-size:13px;}" +
      "#lc-fr-bar input{background:#fff;border:1px solid #dcdcde;color:#1a2420;border-radius:5px;padding:5px 8px;" +
      "font-size:13px;width:150px;font-family:Menlo,Consolas,monospace;}" +
      "#lc-fr-bar input:focus{outline:none;border-color:#7a9e87;box-shadow:0 0 0 2px rgba(122,158,135,0.35);}" +
      "#lc-fr-bar button{background:#f5f7f5;border:1px solid #d5ded6;color:#1a2420;border-radius:5px;cursor:pointer;" +
      "padding:5px 9px;font-size:12px;line-height:1;font-family:inherit;}" +
      "#lc-fr-bar button:hover{background:#eaefea;}" +
      "#lc-fr-bar button:focus-visible{outline:2px solid #7a9e87;outline-offset:1px;}" +
      "#lc-fr-bar button.lc-fr-on{background:#7a9e87;border-color:#5c7f68;color:#fff;}" +
      "#lc-fr-counter{min-width:60px;text-align:center;opacity:0.75;font-variant-numeric:tabular-nums;}" +
      "#lc-fr-counter.lc-fr-error{color:#b3261e;opacity:1;}" +
      "#lc-fr-hint{opacity:0.55;font-size:11px;padding:0 6px;white-space:nowrap;font-style:italic;}" +
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

    var hintEl = document.createElement("span");
    hintEl.id = "lc-fr-hint";
    hintEl.textContent = repeatHint;
    hintEl.title = repeatTitle;

    caseBtn = mkButton("Aa", "Match case", true);
    wordBtn = mkButton("W", "Whole word", true);
    regexBtn = mkButton(".*", "Regular expression", true);

    var prevBtn = mkButton("\u2191", "Previous match (Shift+Enter here, Shift+F3 in editor)", false);
    var nextBtn = mkButton("\u2193", "Next match (Enter here, F3 in editor)", false);
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
    bar.appendChild(hintEl);
    bar.appendChild(closeBtn);
    bar.appendChild(liveEl);
    document.body.appendChild(bar);

    findInput.addEventListener("input", function () { recompute(true, true); });
    findInput.addEventListener("keydown", function (e) {
      if (e.key === "Enter") { e.preventDefault(); navigate(e.shiftKey ? -1 : 1); }
      else if (e.key === "Escape") { e.preventDefault(); closeBar(); }
    });
    replaceInput.addEventListener("keydown", function (e) {
      if (e.key === "Enter") { e.preventDefault(); replaceCurrent(); }
      else if (e.key === "Escape") { e.preventDefault(); closeBar(); }
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
    buildOverlay(textarea);

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
    // Drop the caret at the start of the current match so closing the bar
    // hands back a cursor positioned to edit, not a selection.
    var caret = (current >= 0 && matches[current]) ? matches[current].start : null;
    destroyOverlay();
    matches = [];
    current = -1;
    if (activeTextarea) {
      try {
        activeTextarea.focus();
        if (caret !== null) activeTextarea.setSelectionRange(caret, caret);
      } catch (err) {}
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

  // Fill matches[] from the active box. No scrolling, no focus change, no
  // highlight repaint. Returns false only when the regex itself is invalid.
  function findMatches() {
    matches = [];
    var re = buildRegex();

    if (re === false) {
      counterEl.textContent = "regex?";
      counterEl.classList.add("lc-fr-error");
      announce("Invalid regular expression");
      return false;
    }
    counterEl.classList.remove("lc-fr-error");

    if (!activeTextarea || re === null) return true;

    var value = activeTextarea.value;
    var m;
    var guard = 0;
    while ((m = re.exec(value)) !== null) {
      matches.push({ start: m.index, end: m.index + m[0].length });
      if (m.index === re.lastIndex) re.lastIndex++;
      if (++guard > 100000) break;
    }
    return true;
  }

  function recompute(resetIndex, keepInInput) {
    if (!findMatches()) { paintHighlights(); return; }

    if (resetIndex) current = matches.length ? 0 : -1;
    if (current >= matches.length) current = matches.length - 1;
    if (matches.length && current < 0) current = 0;

    updateCounter();
    paintHighlights();
    if (current >= 0) reveal(current, keepInInput !== false);
  }

  function navigate(delta) {
    if (!matches.length) { recompute(true, true); if (!matches.length) return; }
    current = (current + delta + matches.length) % matches.length;
    updateCounter();
    // Keep focus in the find field while browsing. The highlight layer shows
    // the match, so there is no need to land in the box (which would risk a
    // stray keystroke replacing a selection). To edit, click in or press Esc.
    reveal(current, true);
  }

  function reveal(idx, keepInInput) {
    if (!activeTextarea || idx < 0 || idx >= matches.length) return;
    var start = matches[idx].start;
    try { scrollToOffset(activeTextarea, start); } catch (err) {}
    paintHighlights();
    if (keepInInput && findInput) findInput.focus();
  }

  // Called when the user edits the active box while the bar is open: recompute
  // match positions and repaint, but do not move focus or scroll.
  function onBoxInput() {
    if (!bar || bar.style.display === "none" || !activeTextarea) return;
    findMatches();
    if (current >= matches.length) current = matches.length - 1;
    if (matches.length && current < 0) current = 0;
    if (!matches.length) current = -1;
    updateCounter();
    paintHighlights();
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

  // ---- highlight overlay -----------------------------------------------
  //
  // A layer painted over the box. The match text inside it is transparent and
  // sits exactly above the real text, so the only thing it adds is a tinted
  // background behind each match. pointer-events are off, so clicks, the
  // caret, and typing all go straight to the textarea underneath.

  function ensureOverlayStyles(doc) {
    if (doc.getElementById("lc-fr-overlay-styles")) return;
    var css =
      ".lc-fr-backdrop{position:fixed;overflow:hidden;pointer-events:none;z-index:999998;" +
      "margin:0;padding:0;border:0;background:transparent;}" +
      ".lc-fr-highlights{position:absolute;top:0;left:0;color:transparent;background:transparent;" +
      "white-space:pre-wrap;overflow-wrap:break-word;word-wrap:break-word;border-color:transparent;}" +
      ".lc-fr-mark{background:rgba(122,158,135,0.34);border-radius:2px;color:transparent;}" +
      ".lc-fr-mark.lc-fr-cur{background:rgba(122,158,135,0.55);" +
      "box-shadow:0 0 0 1px rgba(92,127,104,0.95);}";
    var st = doc.createElement("style");
    st.id = "lc-fr-overlay-styles";
    st.textContent = css;
    (doc.head || doc.documentElement).appendChild(st);
  }

  function buildOverlay(textarea) {
    destroyOverlay();
    if (!textarea) return;
    var doc = textarea.ownerDocument;
    try {
      ensureOverlayStyles(doc);
      var bd = doc.createElement("div");
      bd.className = "lc-fr-backdrop";
      var hl = doc.createElement("div");
      hl.className = "lc-fr-highlights";
      bd.appendChild(hl);
      doc.body.appendChild(bd);

      var view = doc.defaultView || window;
      var onInput = function () { onBoxInput(); };
      var onScroll = function () { syncOverlayScroll(); };
      var onWin = function () { positionOverlay(); };
      textarea.addEventListener("input", onInput);
      textarea.addEventListener("scroll", onScroll);
      view.addEventListener("scroll", onWin, true);
      view.addEventListener("resize", onWin);
      var ro = null;
      if (view.ResizeObserver) {
        ro = new view.ResizeObserver(function () { positionOverlay(); });
        ro.observe(textarea);
      }

      overlay = {
        textarea: textarea, doc: doc, view: view,
        backdrop: bd, highlights: hl,
        onInput: onInput, onScroll: onScroll, onWin: onWin, ro: ro
      };
      positionOverlay();
      paintHighlights();
    } catch (err) {
      overlay = null;
    }
  }

  function destroyOverlay() {
    if (!overlay) return;
    try {
      overlay.textarea.removeEventListener("input", overlay.onInput);
      overlay.textarea.removeEventListener("scroll", overlay.onScroll);
      overlay.view.removeEventListener("scroll", overlay.onWin, true);
      overlay.view.removeEventListener("resize", overlay.onWin);
      if (overlay.ro) overlay.ro.disconnect();
      if (overlay.backdrop && overlay.backdrop.parentNode) {
        overlay.backdrop.parentNode.removeChild(overlay.backdrop);
      }
    } catch (err) {}
    overlay = null;
  }

  function positionOverlay() {
    if (!overlay) return;
    var t = overlay.textarea;
    if (!t || (t.isConnected === false)) { destroyOverlay(); return; }
    var rect = t.getBoundingClientRect();
    var bd = overlay.backdrop;
    var hl = overlay.highlights;
    bd.style.left = rect.left + "px";
    bd.style.top = rect.top + "px";
    bd.style.width = rect.width + "px";
    bd.style.height = rect.height + "px";

    var view = overlay.view || window;
    var cs = view.getComputedStyle(t);
    var copy = [
      "fontFamily", "fontSize", "fontWeight", "fontStyle", "fontVariant",
      "lineHeight", "letterSpacing", "textTransform", "textIndent", "tabSize",
      "wordBreak",
      "paddingTop", "paddingRight", "paddingBottom", "paddingLeft",
      "borderTopWidth", "borderRightWidth", "borderBottomWidth", "borderLeftWidth"
    ];
    for (var i = 0; i < copy.length; i++) {
      try { hl.style[copy[i]] = cs[copy[i]]; } catch (e) {}
    }
    hl.style.boxSizing = "border-box";
    hl.style.borderStyle = "solid";
    hl.style.borderColor = "transparent";
    hl.style.whiteSpace = "pre-wrap";
    hl.style.overflowWrap = "break-word";
    hl.style.wordWrap = "break-word";

    // Match the textarea's wrapping width, accounting for any scrollbar that
    // narrows the real content area, so highlights land on the right lines.
    var bl = parseFloat(cs.borderLeftWidth) || 0;
    var br = parseFloat(cs.borderRightWidth) || 0;
    hl.style.width = (t.clientWidth + bl + br) + "px";

    syncOverlayScroll();
  }

  function syncOverlayScroll() {
    if (!overlay) return;
    overlay.highlights.style.transform =
      "translate(" + (-overlay.textarea.scrollLeft) + "px," + (-overlay.textarea.scrollTop) + "px)";
  }

  function escapeHtml(s) {
    return s.replace(/[&<>]/g, function (c) {
      return c === "&" ? "&amp;" : c === "<" ? "&lt;" : "&gt;";
    });
  }

  function buildHighlightHTML(value, list, cur) {
    var out = "";
    var last = 0;
    for (var i = 0; i < list.length; i++) {
      var s = list[i].start, e = list[i].end;
      if (s < last || e <= s) continue;
      out += escapeHtml(value.slice(last, s));
      var cls = (i === cur) ? "lc-fr-mark lc-fr-cur" : "lc-fr-mark";
      out += '<mark class="' + cls + '">' + escapeHtml(value.slice(s, e)) + "</mark>";
      last = e;
    }
    out += escapeHtml(value.slice(last));
    // Keep a trailing line so the last newline renders at the right height.
    if (value.charAt(value.length - 1) === "\n") out += " ";
    return out;
  }

  function paintHighlights() {
    if (!overlay || !activeTextarea || overlay.textarea !== activeTextarea) return;
    overlay.highlights.innerHTML = buildHighlightHTML(activeTextarea.value, matches, current);
    positionOverlay();
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
    syncOverlayScroll();
  }

  // ---- write back into the box -----------------------------------------

  // Plain-textarea writer: the browser's own text-insertion command. It edits
  // the box through the same path as typing, so the change lands in the native
  // undo history (Ctrl+Z / Cmd+Z). Used only for plain textareas, never for the
  // React-controlled block editor. Returns true on success; requires focus.
  function execReplace(textarea, start, end, text) {
    try {
      textarea.focus();
      textarea.setSelectionRange(start, end);
      var doc = textarea.ownerDocument;
      var ok;
      if (text === "") {
        ok = doc.execCommand("delete", false, null);
      } else {
        ok = doc.execCommand("insertText", false, text);
      }
      return !!ok;
    } catch (err) {
      return false;
    }
  }

  // Fallback writer for the rare case execCommand is unavailable. Updates the
  // value and fires input so the block editor stays in sync, but this path is
  // not captured by native undo.
  function setValue(textarea, value, caretAt) {
    var view = textarea.ownerDocument.defaultView || window;
    var setter = Object.getOwnPropertyDescriptor(view.HTMLTextAreaElement.prototype, "value").set;
    setter.call(textarea, value);
    textarea.dispatchEvent(new view.Event("input", { bubbles: true }));
    if (typeof caretAt === "number") {
      try { textarea.setSelectionRange(caretAt, caretAt); } catch (err) {}
    }
  }

  // The block editor controls its field through React. We update it via the
  // value setter (the standard way to drive a controlled field from outside),
  // wrapped so our own writes are not counted as the user editing.
  function setValueSelf(textarea, value, caretAt) {
    selfEditing = true;
    try { setValue(textarea, value, caretAt); }
    finally { selfEditing = false; }
  }

  function pushEditHistory(textarea, before, after) {
    editUndo.push({ textarea: textarea, before: before, after: after });
    if (editUndo.length > 200) editUndo.shift();
    editRedo = [];
  }

  // Revert the most recent replacement this tool made, but only if the box
  // still holds exactly what that replacement produced. If the user changed
  // anything since (or the editor's own undo already moved things), bail and
  // let the editor handle the keystroke.
  function customUndo() {
    if (!editUndo.length || !activeTextarea) return false;
    var top = editUndo[editUndo.length - 1];
    if (top.textarea !== activeTextarea) return false;
    if (activeTextarea.value !== top.after) return false;
    editUndo.pop();
    editRedo.push(top);
    setValueSelf(activeTextarea, top.before, null);
    refreshAfterHistory();
    announce("Undo replacement");
    return true;
  }

  function customRedo() {
    if (!editRedo.length || !activeTextarea) return false;
    var top = editRedo[editRedo.length - 1];
    if (top.textarea !== activeTextarea) return false;
    if (activeTextarea.value !== top.before) return false;
    editRedo.pop();
    editUndo.push(top);
    setValueSelf(activeTextarea, top.after, null);
    refreshAfterHistory();
    announce("Redo replacement");
    return true;
  }

  function refreshAfterHistory() {
    findMatches();
    if (current >= matches.length) current = matches.length - 1;
    if (matches.length && current < 0) current = 0;
    if (!matches.length) current = -1;
    updateCounter();
    paintHighlights();
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

    if (usesNativeUndo(activeTextarea)) {
      if (!execReplace(activeTextarea, start, end, rep)) {
        setValueSelf(activeTextarea, next, start + rep.length);
      }
    } else {
      // Block editor: record our own undo step and write through the setter.
      pushEditHistory(activeTextarea, value, next);
      setValueSelf(activeTextarea, next, start + rep.length);
    }

    findMatches();
    if (matches.length) {
      var target = 0;
      for (var k = 0; k < matches.length; k++) {
        if (matches[k].start >= start) { target = k; break; }
      }
      current = target;
    } else {
      current = -1;
    }
    updateCounter();
    paintHighlights();
    if (current >= 0) reveal(current, true);
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
      if (usesNativeUndo(activeTextarea)) {
        // Plain textarea: select all and insert the new value so the whole
        // replace all is a single step in the browser's native undo history.
        var wrote = false;
        try {
          activeTextarea.focus();
          activeTextarea.setSelectionRange(0, value.length);
          wrote = activeTextarea.ownerDocument.execCommand("insertText", false, next);
        } catch (err) { wrote = false; }
        if (!wrote) setValueSelf(activeTextarea, next, 0);
      } else {
        // Block editor: record our own undo step and write through the setter.
        pushEditHistory(activeTextarea, value, next);
        setValueSelf(activeTextarea, next, 0);
      }
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

    // Undo / redo of replacements this tool made. Plain boxes use native undo,
    // so customUndo/customRedo only act in the block editor and only when the
    // box still holds exactly the post-replace text and focus is in this find
    // session. If they decline, we do not preventDefault, so the editor's own
    // undo handles the keystroke as usual.
    var focusInSession =
      (doc.activeElement === activeTextarea) ||
      (document.activeElement === findInput) ||
      (document.activeElement === replaceInput);

    if (meta && !e.shiftKey && (key === "z" || key === "Z")) {
      if (focusInSession && customUndo()) { e.preventDefault(); e.stopPropagation(); }
      return;
    }
    if ((meta && e.shiftKey && (key === "z" || key === "Z")) ||
        (meta && (key === "y" || key === "Y"))) {
      if (focusInSession && customRedo()) { e.preventDefault(); e.stopPropagation(); }
      return;
    }

    if (key === "Escape" && inBox) {
      e.preventDefault();
      closeBar();
      return;
    }

    // Enter inside the code box is left alone so it inserts a newline like a
    // normal textarea. The match is shown by the highlight layer, not a
    // selection, so nothing gets replaced. Match navigation from inside the
    // box is on F3 / Cmd+G below; Enter in the find field jumps matches.

    // F3 or Cmd/Ctrl+G repeats the find from anywhere, including the box.
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
