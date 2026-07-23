<?php
/**
 * API Explorer View (Phase 0)
 *
 * @package Edmingle_Tutor_Migration\Admin\Views
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$history_obj = new \ETM\Includes\Request_History();
$history = $history_obj->get_history();

?>
<div class="wrap etm-api-explorer">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Edmingle API Explorer', 'edmingle-tutor-migration' ); ?></h1>
	<hr class="wp-header-end">

	<div id="etm-explorer-container">
		
		<!-- Request Builder -->
		<div class="postbox" id="etm-request-builder">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Request Builder', 'edmingle-tutor-migration' ); ?></h2>
			</div>
			<div class="inside">
				<form id="etm-api-form">
					<div class="etm-form-row">
						<select id="etm-api-method" name="api_method">
							<option value="GET">GET</option>
							<option value="POST">POST</option>
							<option value="PUT">PUT</option>
							<option value="PATCH">PATCH</option>
							<option value="DELETE">DELETE</option>
						</select>
						<input type="text" id="etm-api-endpoint" name="endpoint" placeholder="/api/v1/users" required />
						<button type="submit" class="button button-primary" id="etm-api-submit"><?php esc_html_e( 'Send Request', 'edmingle-tutor-migration' ); ?></button>
						<span class="spinner" id="etm-api-spinner" style="float:none; margin-top:0;"></span>
					</div>

					<div class="etm-form-tabs">
						<a href="#" class="etm-tab active" data-target="etm-tab-headers"><?php esc_html_e( 'Headers', 'edmingle-tutor-migration' ); ?></a>
						<a href="#" class="etm-tab" data-target="etm-tab-body"><?php esc_html_e( 'Body', 'edmingle-tutor-migration' ); ?></a>
						<a href="#" class="etm-tab" data-target="etm-tab-query"><?php esc_html_e( 'Query Params', 'edmingle-tutor-migration' ); ?></a>
					</div>

					<div class="etm-tab-content active" id="etm-tab-headers">
						<label for="etm-api-headers"><?php esc_html_e( 'Headers (JSON)', 'edmingle-tutor-migration' ); ?></label>
						<textarea id="etm-api-headers" name="headers" rows="4" placeholder='{"X-Custom-Header": "Value"}'></textarea>
					</div>

					<div class="etm-tab-content" id="etm-tab-body">
						<label for="etm-api-body"><?php esc_html_e( 'Body (JSON)', 'edmingle-tutor-migration' ); ?></label>
						<textarea id="etm-api-body" name="body" rows="6" placeholder='{"name": "John Doe"}'></textarea>
					</div>

					<div class="etm-tab-content" id="etm-tab-query">
						<label for="etm-api-query"><?php esc_html_e( 'Query Parameters (JSON)', 'edmingle-tutor-migration' ); ?></label>
						<textarea id="etm-api-query" name="query" rows="4" placeholder='{"page": "1", "limit": "10"}'></textarea>
					</div>
				</form>
			</div>
		</div>

		<!-- Response Viewer -->
		<div class="postbox" id="etm-response-viewer" style="display:none;">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Response', 'edmingle-tutor-migration' ); ?></h2>
				<button type="button" class="button button-small" id="etm-export-response" style="margin: 10px;"><?php esc_html_e( 'Export JSON', 'edmingle-tutor-migration' ); ?></button>
			</div>
			<div class="inside">
				<div class="etm-response-meta">
					<span class="status-badge" id="etm-res-status"></span>
					<span><strong>Time:</strong> <span id="etm-res-time"></span> ms</span>
					<span><strong>Size:</strong> <span id="etm-res-size"></span> bytes</span>
				</div>
				
				<div class="etm-form-tabs" style="margin-top: 15px;">
					<a href="#" class="etm-res-tab active" data-target="etm-res-pretty"><?php esc_html_e( 'Pretty', 'edmingle-tutor-migration' ); ?></a>
					<a href="#" class="etm-res-tab" data-target="etm-res-raw"><?php esc_html_e( 'Raw', 'edmingle-tutor-migration' ); ?></a>
					<a href="#" class="etm-res-tab" data-target="etm-res-headers"><?php esc_html_e( 'Headers', 'edmingle-tutor-migration' ); ?></a>
				</div>

				<div class="etm-res-tab-content active" id="etm-res-pretty">
					<pre><code id="etm-pretty-code"></code></pre>
				</div>
				
				<div class="etm-res-tab-content" id="etm-res-raw">
					<textarea id="etm-raw-code" rows="10" readonly style="width:100%;"></textarea>
				</div>
				
				<div class="etm-res-tab-content" id="etm-res-headers">
					<pre><code id="etm-headers-code"></code></pre>
				</div>
			</div>
		</div>

		<!-- Request History -->
		<div class="postbox" id="etm-request-history">
			<div class="postbox-header">
				<h2 class="hndle"><?php esc_html_e( 'Request History (Last 20)', 'edmingle-tutor-migration' ); ?></h2>
				<button type="button" class="button button-small" id="etm-clear-history" style="margin: 10px;"><?php esc_html_e( 'Clear All', 'edmingle-tutor-migration' ); ?></button>
			</div>
			<div class="inside">
				<table class="wp-list-table widefat fixed striped" id="etm-history-table">
					<thead>
						<tr>
							<th style="width: 80px;"><?php esc_html_e( 'Method', 'edmingle-tutor-migration' ); ?></th>
							<th><?php esc_html_e( 'Endpoint', 'edmingle-tutor-migration' ); ?></th>
							<th style="width: 80px;"><?php esc_html_e( 'Status', 'edmingle-tutor-migration' ); ?></th>
							<th style="width: 150px;"><?php esc_html_e( 'Date', 'edmingle-tutor-migration' ); ?></th>
							<th style="width: 100px;"><?php esc_html_e( 'Time', 'edmingle-tutor-migration' ); ?></th>
							<th style="width: 150px;"><?php esc_html_e( 'Actions', 'edmingle-tutor-migration' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $history ) ) : ?>
							<tr id="etm-no-history">
								<td colspan="6"><?php esc_html_e( 'No history available.', 'edmingle-tutor-migration' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $history as $index => $item ) : ?>
								<tr data-index="<?php echo esc_attr( $index ); ?>">
									<td><strong><?php echo esc_html( $item['method'] ); ?></strong></td>
									<td><?php echo esc_html( $item['endpoint'] ); ?></td>
									<td><?php echo esc_html( $item['status'] ); ?></td>
									<td><?php echo esc_html( $item['date'] ); ?></td>
									<td><?php echo esc_html( $item['time'] ); ?> ms</td>
									<td>
										<a href="#" class="etm-run-again" data-method="<?php echo esc_attr( $item['method'] ); ?>" data-endpoint="<?php echo esc_attr( $item['endpoint'] ); ?>" data-headers="<?php echo esc_attr( wp_json_encode( $item['headers'] ) ); ?>" data-body="<?php echo esc_attr( wp_json_encode( $item['body'] ) ); ?>" data-query="<?php echo esc_attr( wp_json_encode( $item['query'] ) ); ?>"><?php esc_html_e( 'Run Again', 'edmingle-tutor-migration' ); ?></a> | 
										<a href="#" class="etm-delete-history" style="color: #d63638;"><?php esc_html_e( 'Delete', 'edmingle-tutor-migration' ); ?></a>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>

	</div>
</div>
