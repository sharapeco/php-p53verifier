# P53Verifier

Alert if PHP scripts do not work on PHP 5.3.


## Requirements

- PHP 7.0 or later
- https://github.com/Shaked/php.tools


## Usage

```
php vphp53.php <CWD> <file|dir>
```

On Windows, with vphp53.cmd:

```
vphp53 <file|dir>
```


## Example

```
C:\projects\prarailer\server\lib\admin\controllers\news_edit.php
  Line 19: Short array syntax []
C:\projects\prarailer\server\lib\common\models\rich_page.php
  Line 56: Short array syntax []
C:\projects\prarailer\server\lib\common\models\with_meta_model.php
  Line 89: Short array syntax []
  Line 89: Short array syntax []
  Line 120: Short array syntax []
  Line 145: Short array syntax []
  Line 171: Short array syntax []
  Line 181: Short array syntax []
  Line 187: Short array syntax []
  Line 192: Short array syntax []
  Line 193: Short array syntax []
C:\projects\prarailer\server\lib\public\models\page.php
  Line 13: Short array syntax []
```
