{*
* 2007-2011 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2011 PrestaShop SA
*  @version  Release: $Revision: 8971 $
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*}

{extends file="helper/form/form.tpl"}

{block name="label"}
	{if $input.type == 'color'}
		<div id="colorAttributeProperties" style="display:{if $colorAttributeProperties}block{else}none{/if}";>
	{/if}
	{if isset($input.label)}
		<label>{$input.label} </label>
	{/if}
{/block}


{block name="end_field_block"}
		{if $input.type == 'text' && $input.name == 'texture'}
			</div>
		{/if}
	</div>
		{if $input.name == 'name'}
			{hook h="displayAttributeForm" id_attribute=$form_id}
		{/if}
{/block}

{block name="script"}
	var attributesGroups = {ldelim}{$strAttributesGroups}{rdelim};
	
	var displayColorFieldsOption = function() {
		var val = $('#id_attribute_group').val();
		if (attributesGroups[val])
			$('#colorAttributeProperties').show();
		else
			$('#colorAttributeProperties').hide();
	};
	
	displayColorFieldsOption();
	
	$('#id_attribute_group').change(displayColorFieldsOption);
	
{/block}
