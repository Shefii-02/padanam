import 'package:flutter/material.dart';
import 'package:flutter_widget_from_html_core/flutter_widget_from_html_core.dart';
import 'package:url_launcher/url_launcher.dart';

/// Renders teacher-written HTML (notes, articles, question text) or plain text.
class HtmlText extends StatelessWidget {
  const HtmlText(this.html, {super.key, this.fontSize = 15});
  final String html;
  final double fontSize;

  @override
  Widget build(BuildContext context) {
    final looksHtml = RegExp(r'<[a-z][\s\S]*>', caseSensitive: false).hasMatch(html);
    final style = TextStyle(fontSize: fontSize, height: 1.6);
    if (!looksHtml) return SelectableText(html, style: style);
    return HtmlWidget(
      html,
      textStyle: style,
      onTapUrl: (url) => launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication),
    );
  }
}
