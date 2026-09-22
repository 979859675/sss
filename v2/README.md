# Canvas V2 生产入口

根目录 `/` 和 `/index.php` 现在默认使用 Canvas V2。原 `/v2/` 地址继续兼容，两者复用相同的表单、登录、用户余额、下载次数、订单、福利码和 API。

## 旧版回退入口

- `/` 与 `/index.php`：固定 Canvas V2 渲染器。
- `/v2/`：兼容入口，仍使用同一个 Canvas V2。
- `/legacy.php`：保留原 HTML/CSS 渲染器，用于对比和紧急回退。
- 不新增、删除或重建用户数据表。
- 后台 HTML/CSS 模板仍作为历史内容保留，V2 不读取或改写它们。

## 输出保证

- 每页 Canvas 固定为 `2382×3369`。
- 页面预览挂载的 Canvas 对象就是 PNG 下载使用的对象。
- PDF 将同一个 Canvas 作为 PNG 放入 `210×297mm` 页面。
- V2 不加载或调用 html2canvas。

## 已实现固定渲染器

生产账单代码：

- `de-monese`
- `de-wise`
- `gb-wisegbpstatementuk`（同时兼容旧代码 `gb-wise`）
- `gb-octopusenergybill`（两页）
- `gb-monzo`
- `gb-kraken`（两页）
- `ph-seabank`

另外保留历史代码 `cn-cmb-credit` 的固定渲染器。

V2 不再提供通用样式回退。遇到没有固定渲染器的代码时会明确显示“V2 尚未实现账单类型”，不会生成一个布局错误但看似成功的文件。
