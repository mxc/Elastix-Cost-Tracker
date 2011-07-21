<form method="POST" action="?menu=rates" enctype="multipart/form-data">
<table width="50%" cellpadding="4" cellspacing="0" border="0">
  <tr>
    <td width="50%" align="right">{$pattern.LABEL}: </td>
    <td width="50%" align="left" nowrap>{$pattern.INPUT}</td>
  </tr>
  <tr>
    <td width="50%" align="right">{$rate.LABEL}: </td>
    <td width="50%" align="left" nowrap>{$rate.INPUT}</td>
  </tr>
  <tr>
    <td width="50%" align="center"><input class="button" type="submit" name="save" value="{$Save}" /></td>
    <td width="50%" align="center"><input class="button" type="submit" name="cancel" value="{$Cancel}"  /></td>
  </tr>
</table>
{$id.INPUT}
</form>