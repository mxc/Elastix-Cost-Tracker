<form method="POST" action="?menu=phonebook" enctype="multipart/form-data">
<table width="99%" border="0" cellspacing="0" cellpadding="0" align="center">
    <tr class="moduleTitle">
      <td class="moduleTitle" valign="middle">&nbsp;&nbsp;<img src="images/user_info.png" border="0" align="absmiddle">&nbsp;&nbsp;{$title}</td>
    </tr>
    <tr>
      <td class="error" colspan="2" >{$errorMsg}</td>
    </tr>
    <tr>
        <td>Name:</td><td><input type="text" name="name" size="25" value="{$name}"/></td>
    </tr>
    <tr>
        <td>Number:</td><td><input type="text" name="number" size="25" value="{$number}" /></td>
    </tr>
    <tr>
        <td colspan="2">
            <input class="button" type="submit" name="save" value="Save" >
            <input class="button" type="submit" name="cancel" value="Cancel" >
        </td>
    </tr>
</table>
    <input type="hidden" name="id" value="{$id}" />
</form>