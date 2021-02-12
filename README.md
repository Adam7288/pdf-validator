# pdf-validator
Validates pdf files, checks for password protection and max page count

Works with linux / unix. Prerequisites: qpdf 10.0+, mupdf cli tools (https://www.mupdf.com/)


## Usage
```php

$validator = new PdfValidator("/somefile/path");
$validator->setMaxPages(100); //will reject pdf files > 100 pages
if($validator->isValid()) {
  echo "file valid";
} else {
  echo "not valid: " . $validator->getError();
}

```

Enjoy!
