{ if !empty($script) }
<script language="JavaScript">
    {$script}
</script>
{/if}
{if (!empty($constraintViolation))}
<span style="color:red">{$constraintViolation}</span><br/>
{/if}
<form method="POST" action="?menu=userpinmanagement" enctype="multipart/form-data">
<table width="99%" border="0" cellspacing="0" cellpadding="0" align="center">
<tr>
  <td>
    <table width="100%" cellpadding="4" cellspacing="0" border="0">
      <tr>
        <td  align="right">{$pinset1.LABEL}:</td>
        <td  align="left" nowrap>{$pinset1.INPUT}</td>
      </tr>
      <tr>
        <td align="right">{$pin1.LABEL}:</td>
        <td align="left" nowrap>{$pin1.INPUT} { if !empty($autogen) }{$autogen}{/if}</td>
      </tr>
      <tr>
        <td align="right">{$username1.LABEL}:</td>
        <td align="left" nowrap>{$username1.INPUT}</td>
      </tr>
      {if !empty($startDate)}
          <tr>
            <td align="right">{$startDate.LABEL}:</td>
            <td align="left" nowrap>{$startDate.INPUT}</td>
          </tr>
          <tr>
            <td align="right">{$endDate.LABEL}:</td>
            <td align="left" nowrap>{$endDate.INPUT}</td>
          </tr>
      {/if}
      <tr>
        <td>&nbsp;</td>
        <td align="left" nowrap>{$pinset_id.INPUT}</td>
      </tr>
      <tr>
        <td align="center"><input class="button" type="submit" name="cancel" value="{$Cancel}"  /></td>
        <td align="center"><input class="button" type="submit" name="save" value="{$Submit}"  /><input type="hidden" name="type" value="{$type}"/></td>
      </tr>
   </table>
  </td>
</tr>
</table>
</form>