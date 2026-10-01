import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/theme/app_theme.dart';
import '../../../core/utils/json.dart';
import '../../../core/widgets/async_view.dart';
import '../../../core/widgets/ui.dart';
import '../data/store_repository.dart';

/// Profile → Orders & invoices.
class OrdersScreen extends ConsumerWidget {
  const OrdersScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final p = context.palette;
    return SubPage(
      title: 'Orders & invoices',
      body: AsyncView(
        value: ref.watch(myOrdersProvider),
        onRetry: () => ref.invalidate(myOrdersProvider),
        data: (page) => page.items.isEmpty
            ? const EmptyView(emoji: '🧾', title: 'No orders yet', subtitle: 'Your course purchases and GST invoices appear here.')
            : RefreshIndicator(
                onRefresh: () => ref.refresh(myOrdersProvider.future),
                child: ListView.separated(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                  itemCount: page.items.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 12),
                  itemBuilder: (_, i) {
                    final o = page.items[i];
                    final inv = o.m('invoice');
                    final url = inv.sn('url');
                    return AppCard(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Expanded(
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text(o.s('course'), style: const TextStyle(fontWeight: FontWeight.w800)),
                              Text('${o.s('batch')} · ${o.s('order_no')}', style: TextStyle(fontSize: 12.5, color: p.muted)),
                              Text(o.s('paid_at', o.s('created_at')).split('T').first, style: TextStyle(fontSize: 12, color: p.muted)),
                            ]),
                          ),
                          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
                            Text(o.s('total'), style: const TextStyle(fontWeight: FontWeight.w800)),
                            Tag(o.s('status'), tone: o.s('status') == 'paid' ? Tone.mint : Tone.peach),
                          ]),
                        ]),
                        if (url != null) ...[
                          const SizedBox(height: 10),
                          Row(children: [
                            Expanded(child: AppButton('Invoice PDF', small: true, outlined: true, onPressed: () => launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication))),
                            const SizedBox(width: 8),
                            Expanded(child: AppButton('Share', small: true, outlined: true, onPressed: () => SharePlus.instance.share(ShareParams(text: 'Padanam invoice ${inv.s('no')}: $url')))),
                          ]),
                        ],
                      ]),
                    );
                  },
                ),
              ),
      ),
    );
  }
}
