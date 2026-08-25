# The fields

## Options every field accepts

| Key | Default | Description |
|---|---|---|
| `type` | — | Required. Field type |
| `name` | — | Required. Storage key |
| `label` | `''` | Visible label |
| `instructions` | `''` | Help text below the label |
| `required` | `false` | Shows the asterisk and adds `required` to the control |
| `default_value` | `''` | Value used before anything has been saved |
| `placeholder` | `''` | Placeholder text |
| `wrapper` | `array()` | `width` as a percentage, plus extra `class` and `id` |
| `conditional_logic` | `array()` | Rules deciding whether the field is shown |

## Available types

| Type | Own options | Notes |
|---|---|---|
| `text` | `maxlength`, `prepend`, `append` | |
| `textarea` | `rows`, `maxlength` | Keeps line breaks |
| `number` | `min`, `max`, `step`, `prepend`, `append` | Empty is stored as `''`, not `0` |
| `range` | `min`, `max`, `step`, `prepend`, `append` | Slider with a synced number input |
| `email` | `maxlength`, `prepend`, `append` | Sanitised with `sanitize_email()` |
| `url` | `maxlength`, `prepend`, `append` | Sanitised with `sanitize_url()`; print with `esc_url()` |
| `password` | `maxlength` | Stored in plain text; don't use it for credentials |
| `select` | `choices`, `multiple`, `allow_null` | Native, with fixed options |
| `radio` | `choices`, `layout`, `allow_null` | |
| `checkbox` | `choices`, `layout` | Stores an array |
| `button_group` | `choices`, `layout`, `allow_null` | Radios styled as segmented buttons |
| `true_false` | `message`, `ui`, `ui_on_text`, `ui_off_text` | Stores `1` or `0`; `ui` draws the switch |
| `date_picker` | `return_format`, `min`, `max` | Native control; stored as `Ymd` |
| `time_picker` | `return_format`, `min`, `max` | Native control; stored as `H:i:s` |
| `date_time_picker` | `return_format`, `min`, `max` | Native control; stored as `Y-m-d H:i:s` |
| `color_picker` | `enable_opacity`, `palette` | Core picker; hex, or `rgba()` with opacity |
| `wysiwyg` | `tabs`, `toolbar`, `rows`, `media_upload`, `table` | TinyMCE; works inside repeaters too |
| `link` | `return_format` | Core link modal; stores text, URL and target |
| `oembed` | `width`, `height`, `return_format` | Stores the URL; the HTML resolves at render time |
| `image` | `preview_size`, `library`, `mime_types`, `return_format` | Stores the ID; validated against the media library |
| `file` | `library`, `mime_types`, `return_format` | Stores the attachment ID |
| `gallery` | `preview_size`, `min`, `max`, `mime_types`, `return_format` | Ordered list of images |
| `icon_picker` | `collections`, `return_format` | Iconify search; stores `mdi:home` |
| `message` | `message`, `esc_html`, `new_lines` | Presentation only, stores nothing |
| `separator` | — | Presentation only; the label titles the section |
| `tab` | `selected`, `endpoint` | Groups the fields that follow into a tab |
| `accordion` | `open`, `multi_expand`, `endpoint` | Nests the fields that follow into a collapsible panel |
| `repeater` | `sub_fields`, `min`, `max`, `button_label` | List of rows; compatible with ACF's data |
| `group` | `sub_fields`, `layout` | Sub-fields under a shared name, without repetition |
| `flexible_content` | `layouts`, `min`, `max`, `button_label` | Rows of different shapes, chosen by the editor |
| `clone` | `clone`, `display`, `prefix_name`, `prefix_label`, `overrides` | Pulls in a field set declared elsewhere |
| `post_object` | `post_type`, `taxonomy`, `post_status`, `multiple` | Stores the post ID; searches over AJAX |
| `page_link` | those of `post_object` | Stores the ID; returns the permalink |
| `relationship` | `filters`, `min`, `max`, plus those of `post_object` | Two panels; preserves order |
| `taxonomy` | `taxonomy`, `field_type`, `hide_empty` | Checkboxes, radios or a dropdown |
| `user` | `role`, `multiple` | Stores the user ID |

Every one of them also accepts `readonly` and `disabled`.

## What each field returns

WordPress hands back every meta value as a string. Forja returns them with their
native type:

| Type | Returns | When empty |
|---|---|---|
| `number` | `int` or `float` | `null` |
| `range` | `int` or `float` | the minimum |
| `true_false` | `bool` | `false` |
| `image`, `file` | per `return_format` | `0`, `''` or `null` |
| `checkbox`, multiple `select` | `array` | `array()` |
| `repeater`, `flexible_content` | `array` of rows | `array()` |
| `group` | `array` keyed by sub-field | `array()` |

An empty `number` returns `null` and not `0` on purpose: zero is a legitimate
value, and conflating them would make "they didn't touch it" indistinguishable
from "they typed zero".

::: tip Going deeper
Repeaters, flexible content, `clone`, conditional logic, the relational fields
and the storage formats are documented in Spanish. Start at
[Los campos](/campos/).
:::
