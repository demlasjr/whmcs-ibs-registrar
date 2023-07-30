<div style="width:1000px;">
<h3 style="margin-bottom:25px;">DNSSEC Management <span class="label label-default">{if $disabled}{Lang::trans("disabled")}{else}{Lang::trans("enabled")}{/if}</span></h3>

{if $successful}
	<div class="alert alert-success text-center">{Lang::trans("changessavedsuccessfully")}</div>
{/if}

{if $error}
	<div class="alert alert-danger text-center">{$error}</div>
{/if}

<div class="alert alert-info">
    Be sure you know what you are doing here. Any mistake could render your domain unusable!
</div>

<form method="POST" action="">
<input type="hidden" name="action" value="domaindetails">
<input type="hidden" name="id" value="{$domainid}">
<input type="hidden" name="modop" value="custom">
<input type="hidden" name="a" value="dnssec">
<input type="hidden" name="submit" id="isubmit" value="1">

<h4>DS records</h4>
<table class="table table-striped">
	<thead>
	    <tr>
			<th style="width:100px;">TTL</th>
	        <th style="width:100px;">Key Tag</th>
	        <th style="width:250px;">Algorithm</th>
	        <th style="width:180px;">Digest Type</th>
	        <th style="width:200px;">Digest</th>
		</tr>
	</thead>
	<tbody>
		{foreach item=ds from=$secdnsds name=secdnsds}
		<tr>
			<td><input class="form-control" type="text" name="SECDNS-DS[{$smarty.foreach.secdnsds.index}][ttl]" value="{$ds.ttl}"></td>
			<td><input class="form-control" type="text" name="SECDNS-DS[{$smarty.foreach.secdnsds.index}][keytag]" value="{$ds.keytag}"></td>
			<td>
				<select name="SECDNS-DS[{$smarty.foreach.secdnsds.index}][alg]" class="form-control">
				{foreach $algOptions as $val => $name}
					{if $val eq ""}
						<option value="{$val}">{$name}</option>
					{else}
						<option value="{$val}"{if $val eq $ds.alg} selected{/if}>[{$val}] {$name}</option>
					{/if}
				{/foreach}
				</select>
			</td>
			<td>
				<select name="SECDNS-DS[{$smarty.foreach.secdnsds.index}][digesttype]" class="form-control">
				{foreach $digestOptions as $val => $name}
					{if $val eq ""}
						<option value="{$val}">{$name}</option>
					{else}
						<option value="{$val}" {if $val eq $ds.digesttype} selected{/if}>[{$val}] {$name}</option>
					{/if}
				{/foreach}
                </select>
			</td>
			<td><input class="form-control" type="text" name="SECDNS-DS[{$smarty.foreach.secdnsds.index}][digest]" value="{$ds.digest}"></td>
		</tr>
		{/foreach}
		<tr>
			<td><input class="form-control" type="text" name="SECDNS-DS[{$smarty.foreach.secdnsds.index+1}][ttl]" value="" placeholder="3600"></td>
			<td><input class="form-control" type="text" name="SECDNS-DS[{$smarty.foreach.secdnsds.index+1}][keytag]" value=""></td>
			<td>
				<select name="SECDNS-DS[{$smarty.foreach.secdnsds.index+1}][alg]" class="form-control">
				{foreach $algOptions as $val => $name}
					{if $val eq ""}
						<option value="{$val}">{$name}</option>
					{else}
						<option value="{$val}">[{$val}] {$name}</option>
					{/if}
				{/foreach}
				</select>
			</td>
			<td>
				<select name="SECDNS-DS[{$smarty.foreach.secdnsds.index+1}][digesttype]" class="form-control">
				{foreach $digestOptions as $val => $name}
					{if $val eq ""}
						<option value="{$val}">{$name}</option>
					{else}
						<option value="{$val}">[{$val}] {$name}</option>
					{/if}
				{/foreach}
                </select>

			</td>
			<td>
				<input class="form-control" type="text" name="SECDNS-DS[{$smarty.foreach.secdnsds.index+1}][digest]" value=""></td>
		</tr>
	</tbody>
</table>

<h4>KEY records</h4>
<small>Only some TLDs want the DNSKEY record e.g. .cz, .de, .be, .eu. Otherwise the DS Record has to be used.</small>
<table class="table table-striped">
	<thead>
	    <tr>
			<th style="width:100px;">TTL</th>
	        <th style="width:250px;">Flags</th>
	        <th style="width:175px;">Protocol</th>
	        <th style="width:250px;">Algorithm</th>
	        <th style="width:200px;">Public Key</th>
		</tr>
	</thead>
	<tbody>
		{foreach item=key from=$secdnskey name=secdnskey}
		<tr>
			<td><input class="form-control" type="text" name="SECDNS-KEY[{$smarty.foreach.secdnskey.index}][ttl]" value="{$key.ttl}"></td>
			<td>
				<select name="SECDNS-KEY[{$smarty.foreach.secdnskey.index}][flags]" class="form-control">
				{foreach $flagOptions as $val => $name}
					{if $val eq ""}
						<option value="{$val}">{$name}</option>
					{else}
						<option value="{$val}" {if $val eq $key.flags} selected{/if}>[{$val}] {$name}</option>
					{/if}
				{/foreach}
                </select>
			</td>
			<td>
				<select name="SECDNS-KEY[{$smarty.foreach.secdnskey.index}][protocol]" class="form-control">
				{foreach $protocolOptions as $val => $name}
					{if $val eq ""}
						<option value="{$val}">{$name}</option>
					{else}
						<option value="{$val}"{if $val eq $key.protocol} selected{/if}>[{$val}] {$name}</option>
					{/if}
				{/foreach}
                </select>
			</td>
			<td>
				<select name="SECDNS-KEY[{$smarty.foreach.secdnskey.index}][alg]" class="form-control">
				{foreach $algOptions as $val => $name}
					{if $val eq ""}
						<option value="{$val}">{$name}</option>
					{else}
						<option value="{$val}"{if $val eq $key.alg} selected{/if}>[{$val}] {$name}</option>
					{/if}
				{/foreach}
				</select>
			</td>
			<td><input class="form-control" type="text" name="SECDNS-KEY[{$smarty.foreach.secdnskey.index}][pubkey]" value="{$key.pubkey}"></td>
		</tr>
		{/foreach}
		<tr>
			<td><input class="form-control" type="text" name="SECDNS-KEY[{$smarty.foreach.secdnskey.index+1}][ttl]" value="" placeholder="3600"></td>
			<td>
				<select name="SECDNS-KEY[{$smarty.foreach.secdnskey.index+1}][flags]" class="form-control">
                    {foreach $flagOptions as $val => $name}
						{if $val eq ""}
                        	<option value="{$val}">{$name}</option>
						{else}
                    		<option value="{$val}">[{$val}] {$name}</option>
						{/if}
                    {/foreach}
                </select>
			</td>
			<td>
				<select name="SECDNS-KEY[{$smarty.foreach.secdnskey.index+1}][protocol]" class="form-control">
					{foreach $protocolOptions as $val => $name}
						{if $val eq ""}
							<option value="{$val}">{$name}</option>
						{else}
                    		<option value="{$val}">[{$val}] {$name}</option>
						{/if}
					{/foreach}
                </select>
			</td>
			<td>
				<select name="SECDNS-KEY[{$smarty.foreach.secdnskey.index+1}][alg]" class="form-control">
				{foreach $algOptions as $val => $name}
					{if $val eq ""}
						<option value="{$val}">{$name}</option>
					{else}
						<option value="{$val}">[{$val}] {$name}</option>
					{/if}
				{/foreach}
				</select>
			</td>
			<td><input class="form-control" type="text" name="SECDNS-KEY[{$smarty.foreach.secdnskey.index+1}][pubkey]" value=""></td>
		</tr>
	</tbody>
</table>

<p class="text-center">
    <input class="btn btn-large btn-primary" type="submit" value="{Lang::trans('clientareasavechanges')}">
	<input class="btn btn-large btn-primary" type="button" onclick="$('#isubmit').val('0');HTMLFormElement.prototype.submit.call(this.form);" value="{Lang::trans('disable')}">
</p>

</form>

<h5>TTL</h5>
<p>The time-to-live in seconds. It specifies how long a resolver is supposed to cache or remember the DNS query before the query expires and a new one needs to be done.</p>

<h5>Key Tag</h5>
<p>A short numeric value which can help quickly identify the referenced DNSKEY-record.</p>

<h5>Flags</h5>
<p>"Zone Key" (set for all DNSSEC keys) and "Secure Entry Point" (set for KSK and simple keys).</p>

<h5>Protocol</h5>
<p>The Protocol field must have value 3 / DNSSEC.</p>

<h5>Algorithm</h5>
<p>For DS Records: The algorithm of the referenced DNSKEY Record.</p>
<p>For DNSKEY Records: The Algorithm field identifies the public key's cryptographic algorithm and determines the format of the Public Key field.</p>

<h5>Digest Type</h5>
<p>Cryptographic hash algorithm used to create the Digest value.</p>

<h5>Digest</h5>
<p>A cryptographic hash value of the referenced DNSKEY-record.</p>

<h5>Public Key</h5>
<p>Public key data.</p>
</div>