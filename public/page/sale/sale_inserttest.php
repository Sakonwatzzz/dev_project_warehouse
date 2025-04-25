<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Word 2007 Ribbon Mockup</title>
  <style>
    /* ---- Reset & Base ---- */
    * { margin:0; padding:0; box-sizing: border-box; }
    body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; font-size: 12px; color: #333; }

    /* ---- Ribbon Container ---- */
    .ribbon {
      background: #bdd0ee; /* พื้นหลังหลัก */
      border-bottom: 1px solid #6b90da;
      padding: 2px 4px;
    }
    .ribbon-tabs {
      display: flex;
      gap: 2px;
      margin-bottom: 2px;
    }
    .ribbon-tab {
      padding: 4px 10px;
      background: linear-gradient(to bottom, #e0ecff, #c3d4f5);
      border: 1px solid #8fabe5;
      border-bottom: none;
      border-radius: 3px 3px 0 0;
      cursor: pointer;
      color: #1f497d;
      font-size: 12px;
      user-select: none;
    }
    .ribbon-tab.active {
      background: linear-gradient(to bottom, #ffffff, #f5f9ff);
      color: #245683;
      font-weight: bold;
    }
    .ribbon-tab:hover:not(.active) {
      background: linear-gradient(to bottom, #e8f1ff, #d0dff7);
    }
    .ribbon-content {
      background: linear-gradient(to bottom, #eef3fd, #d6e3f5);
      border: 1px solid #8fabe5;
      border-radius: 0 3px 3px 3px;
      padding: 4px 2px 2px 2px;
      display: none;
      height: 94px;
    }
    .ribbon-content.active {
      display: flex;
      gap: 4px;
    }

    /* ---- Groups inside Ribbon ---- */
    .group {
      border-right: 1px solid #c0d3ed;
      padding: 2px 6px;
      display: flex;
      flex-direction: column;
      align-items: center;
      min-width: 70px;
      position: relative;
      height: 86px;
    }
    .group:last-child { border-right: none; }

    .group .group-title {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      text-align: center;
      font-size: 11px;
      color: #4a6fa5;
      padding: 2px 0;
      border-top: 1px dotted #c0d3ed;
    }

    /* ---- Button Layouts ---- */
    .btn-container {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 2px;
      margin-bottom: 18px; /* ที่ว่างสำหรับชื่อกลุ่ม */
    }

    /* ---- Buttons ---- */
    .btn {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      background: transparent;
      border: 1px solid transparent;
      border-radius: 3px;
      cursor: pointer;
      transition: all 0.2s;
      padding: 2px;
    }
    .btn:hover {
      background: linear-gradient(to bottom, #fefefe, #e2edff);
      border: 1px solid #b9d0f7;
    }
    .btn:active {
      background: linear-gradient(to bottom, #e2edff, #fefefe);
    }

    /* Large button */
    .btn-large {
      width: 45px;
      height: 60px;
    }
    .btn-medium {
      width: 36px;
      height: 36px;
    }
    .btn-small {
      width: 28px;
      height: 28px;
    }

    .btn-icon {
      font-size: 22px;
      color: #3a70c1;
      margin-bottom: 2px;
    }
    .btn-medium .btn-icon, .btn-small .btn-icon {
      font-size: 16px;
      margin-bottom: 0;
    }
    .btn-label {
      font-size: 10px;
      text-align: center;
      color: #1f497d;
    }
    .btn-medium .btn-label, .btn-small .btn-label {
      font-size: 9px;
    }

    /* ---- Split Buttons ---- */
    .split-btn {
      display: flex;
      flex-direction: column;
      border: 1px solid transparent;
      border-radius: 3px;
      width: 45px;
    }
    .split-btn:hover {
      border: 1px solid #b9d0f7;
    }
    .split-btn-main {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 2px;
      cursor: pointer;
      background: transparent;
    }
    .split-btn-main:hover {
      background: linear-gradient(to bottom, #fefefe, #e2edff);
    }
    .split-btn-arrow {
      border-top: 1px solid #dde8f7;
      text-align: center;
      cursor: pointer;
      font-size: 8px;
      color: #3a70c1;
      padding: 1px 0;
    }
    .split-btn-arrow:hover {
      background: #e2edff;
    }

    /* ---- Font Selector ---- */
    .font-selector {
      display: flex;
      flex-direction: column;
      gap: 2px;
      margin-bottom: 18px;
      width: 100%;
    }
    .font-dropdown {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border: 1px solid #b9d0f7;
      background: linear-gradient(to bottom, #fefefe, #eef3fc);
      padding: 3px 5px;
      border-radius: 3px;
      cursor: pointer;
      font-size: 11px;
    }
    .font-dropdown:hover {
      background: linear-gradient(to bottom, #fefefe, #e2edff);
    }
    .font-size {
      width: 40px;
    }
  </style>
</head>
<body>

  <div class="ribbon">
    <!-- แท็บต่างๆ -->
    <div class="ribbon-tabs">
      <div class="ribbon-tab active" data-target="home">Home</div>
      <div class="ribbon-tab" data-target="insert">Insert</div>
      <div class="ribbon-tab" data-target="page-layout">Page Layout</div>
      <div class="ribbon-tab" data-target="references">References</div>
      <div class="ribbon-tab" data-target="mailings">Mailings</div>
      <div class="ribbon-tab" data-target="review">Review</div>
      <div class="ribbon-tab" data-target="view">View</div>
    </div>

    <!-- เนื้อหาแต่ละแท็บ -->
    <div id="home" class="ribbon-content active">
      <!-- กลุ่ม Clipboard -->
      <div class="group">
        <div class="btn-container">
          <div class="split-btn">
            <div class="split-btn-main">
              <div class="btn-icon">📋</div>
              <div class="btn-label">Paste</div>
            </div>
            <div class="split-btn-arrow">▼</div>
          </div>
        </div>
        <div class="btn-container">
          <div class="btn btn-small">
            <div class="btn-icon">✂️</div>
            <div class="btn-label">Cut</div>
          </div>
          <div class="btn btn-small">
            <div class="btn-icon">📑</div>
            <div class="btn-label">Copy</div>
          </div>
          <div class="btn btn-small">
            <div class="btn-icon">🖌️</div>
            <div class="btn-label">Format</div>
          </div>
        </div>
        <div class="group-title">Clipboard</div>
      </div>
      
      <!-- กลุ่ม Font -->
      <div class="group">
        <div class="font-selector">
          <div class="font-dropdown">Calibri</div>
          <div class="font-dropdown font-size">11</div>
        </div>
        <div class="btn-container">
          <div class="btn btn-small">
            <div class="btn-icon" style="font-weight: bold;">B</div>
          </div>
          <div class="btn btn-small">
            <div class="btn-icon" style="font-style: italic;">I</div>
          </div>
          <div class="btn btn-small">
            <div class="btn-icon" style="text-decoration: underline;">U</div>
          </div>
          <div class="btn btn-small">
            <div class="btn-icon">A̱</div>
          </div>
          <div class="btn btn-small">
            <div class="btn-icon" style="color: #c00;">A</div>
          </div>
          <div class="btn btn-small">
            <div class="btn-icon" style="background: #ffff00; padding: 0 2px;">Ab</div>
          </div>
        </div>
        <div class="group-title">Font</div>
      </div>
      
      <!-- กลุ่ม Paragraph -->
      <div class="group">
        <div class="btn-container">
          <div class="btn btn-small">
            <div class="btn-icon">•</div>
          </div>
          <div class="btn btn-small">
            <div class="btn-icon">1.</div>
          </div>
          <div class="btn btn-small">
            <div class="btn-icon">↑</div>
          </div>
          <div class="btn btn-small">
            <div class="btn-icon">↓</div>
          </div>
          <div class="btn btn-small">
            <div class="btn-icon">⇌</div>
          </div>
          <div class="btn btn-small">
            <div class="btn-icon">⌧</div>
          </div>
        </div>
        <div class="btn-container">
          <div class="btn btn-small">
            <div class="btn-icon">≡</div>
          </div>
          <div class="btn btn-small">
            <div class="btn-icon">≡|</div>
          </div>
          <div class="btn btn-small">
            <div class="btn-icon">|≡</div>
          </div>
          <div class="btn btn-small">
            <div class="btn-icon">≡⫶</div>
          </div>
        </div>
        <div class="group-title">Paragraph</div>
      </div>
      
      <!-- กลุ่ม Styles -->
      <div class="group">
        <div class="btn-container">
          <div class="btn btn-large">
            <div class="btn-icon" style="font-size: 18px;">Aa</div>
            <div class="btn-label">Quick Styles</div>
          </div>
        </div>
        <div class="group-title">Styles</div>
      </div>
      
      <!-- กลุ่ม Editing -->
      <div class="group">
        <div class="btn-container">
          <div class="btn btn-large">
            <div class="btn-icon">🔍</div>
            <div class="btn-label">Find</div>
          </div>
          <div class="btn btn-large">
            <div class="btn-icon">🔄</div>
            <div class="btn-label">Replace</div>
          </div>
          <div class="btn btn-large">
            <div class="btn-icon">📋</div>
            <div class="btn-label">Select</div>
          </div>
        </div>
        <div class="group-title">Editing</div>
      </div>
    </div>

    <div id="insert" class="ribbon-content">
      <div class="group">
        <div class="btn-container">
          <div class="btn btn-large">
            <div class="btn-icon">📄</div>
            <div class="btn-label">Cover Page</div>
          </div>
          <div class="btn btn-large">
            <div class="btn-icon">📃</div>
            <div class="btn-label">Blank Page</div>
          </div>
        </div>
        <div class="group-title">Pages</div>
      </div>
      <div class="group">
        <div class="btn-container">
          <div class="btn btn-large">
            <div class="btn-icon">🖼️</div>
            <div class="btn-label">Picture</div>
          </div>
          <div class="btn btn-large">
            <div class="btn-icon">📊</div>
            <div class="btn-label">Chart</div>
          </div>
        </div>
        <div class="group-title">Illustrations</div>
      </div>
    </div>

    <div id="page-layout" class="ribbon-content">
      <div class="group">
        <div class="btn-container">
          <div class="btn btn-large">
            <div class="btn-icon">📏</div>
            <div class="btn-label">Margins</div>
          </div>
          <div class="btn btn-large">
            <div class="btn-icon">📄</div>
            <div class="btn-label">Orientation</div>
          </div>
        </div>
        <div class="group-title">Page Setup</div>
      </div>
    </div>

    <div id="references" class="ribbon-content">
      <div class="group">
        <div class="btn-container">
          <div class="btn btn-large">
            <div class="btn-icon">📑</div>
            <div class="btn-label">Table of Contents</div>
          </div>
        </div>
        <div class="group-title">Table of Contents</div>
      </div>
    </div>

    <div id="mailings" class="ribbon-content">
      <div class="group">
        <div class="btn-container">
          <div class="btn btn-large">
            <div class="btn-icon">✉️</div>
            <div class="btn-label">Envelopes</div>
          </div>
          <div class="btn btn-large">
            <div class="btn-icon">🏷️</div>
            <div class="btn-label">Labels</div>
          </div>
        </div>
        <div class="group-title">Create</div>
      </div>
    </div>

    <div id="review" class="ribbon-content">
      <div class="group">
        <div class="btn-container">
          <div class="btn btn-large">
            <div class="btn-icon">📝</div>
            <div class="btn-label">Spelling & Grammar</div>
          </div>
        </div>
        <div class="group-title">Proofing</div>
      </div>
    </div>

    <div id="view" class="ribbon-content">
      <div class="group">
        <div class="btn-container">
          <div class="btn btn-large">
            <div class="btn-icon">📄</div>
            <div class="btn-label">Print Layout</div>
          </div>
          <div class="btn btn-large">
            <div class="btn-icon">📃</div>
            <div class="btn-label">Full Screen</div>
          </div>
        </div>
        <div class="group-title">Document Views</div>
      </div>
      <div class="group">
        <div class="btn-container">
          <div class="btn btn-large">
            <div class="btn-icon">🔍</div>
            <div class="btn-label">Zoom</div>
          </div>
          <div class="btn btn-large">
            <div class="btn-icon">100%</div>
            <div class="btn-label">100%</div>
          </div>
        </div>
        <div class="group-title">Zoom</div>
      </div>
    </div>
  </div>

  <script>
    // สคริปต์สลับแท็บ
    document.querySelectorAll('.ribbon-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        // ติ๊กออกทุกแท็บ และคอนเทนต์
        document.querySelectorAll('.ribbon-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.ribbon-content').forEach(c => c.classList.remove('active'));
        // เปิดแท็บที่คลิก
        tab.classList.add('active');
        document.getElementById(tab.dataset.target).classList.add('active');
      });
    });
  </script>

</body>
</html>