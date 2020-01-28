<?php
$this->data['header'] = 'Error in processing affiliation information';

$this->includeAtTemplateBase('includes/header.php');
?>
<h1>Oops! Something went wrong.</h1>

An unexpected error occurred while processing affiliation information. The exception was:
<pre>

<?php
    echo $this->data['e'];
?>
</pre>

<?php
$this->includeAtTemplateBase('includes/footer.php');
