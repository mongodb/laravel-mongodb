import{_ as s,c as e,o as a,N as o}from"./chunks/framework.8a749e37.js";const _=JSON.parse('{"title":"Soft Deletes","description":"","frontmatter":{},"headers":[],"relativePath":"v3/guide/eloquent/soft-deletes.md"}'),n={name:"v3/guide/eloquent/soft-deletes.md"},t=o(`<h1 id="soft-deletes" tabindex="-1">Soft Deletes <a class="header-anchor" href="#soft-deletes" aria-label="Permalink to &quot;Soft Deletes&quot;">​</a></h1><p>When soft deleting a model, it is not actually removed from your database. Instead, a deleted_at timestamp is set on the record.</p><p>To enable soft deletes for a model, apply the <code>Jenssegers\\Mongodb\\Eloquent\\SoftDeletes</code> Trait to the model:</p><div class="language-php"><button title="Copy Code" class="copy"></button><span class="lang">php</span><pre class="shiki material-theme-palenight"><code><span class="line"><span style="color:#F78C6C;">use</span><span style="color:#FFCB6B;"> </span><span style="color:#A6ACCD;">Jenssegers</span><span style="color:#89DDFF;">\\</span><span style="color:#A6ACCD;">Mongodb</span><span style="color:#89DDFF;">\\</span><span style="color:#A6ACCD;">Eloquent</span><span style="color:#89DDFF;">\\</span><span style="color:#A6ACCD;">SoftDeletes</span><span style="color:#89DDFF;">;</span></span>
<span class="line"></span>
<span class="line"><span style="color:#C792EA;">class</span><span style="color:#A6ACCD;"> </span><span style="color:#FFCB6B;">User</span><span style="color:#A6ACCD;"> </span><span style="color:#C792EA;">extends</span><span style="color:#A6ACCD;"> </span><span style="color:#FFCB6B;">Model</span></span>
<span class="line"><span style="color:#89DDFF;">{</span></span>
<span class="line"><span style="color:#A6ACCD;">    </span><span style="color:#F78C6C;">use</span><span style="color:#FFCB6B;"> </span><span style="color:#A6ACCD;">SoftDeletes</span><span style="color:#89DDFF;">;</span></span>
<span class="line"><span style="color:#89DDFF;">}</span></span>
<span class="line"></span></code></pre></div><p>For more information check <a href="http://laravel.com/docs/eloquent#soft-deleting" target="_blank" rel="noreferrer">Laravel Docs about Soft Deleting</a>.</p>`,5),l=[t];function p(r,c,d,i,D,C){return a(),e("div",null,l)}const f=s(n,[["render",p]]);export{_ as __pageData,f as default};
