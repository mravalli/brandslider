{if $brandslider.slides}
  <section id="brand_slider" class="col-xs-12 block clearfix">
      <div class="brandslides">
          {foreach from=$brandslider.slides item=slide}
            <div>
              <a href="{$slide.url}">
                <img src="{$slide.image_url}" alt="{$slide.legend|escape}" />
              </a>
            </div>
          {/foreach}
      </div>
  </section>
{/if}