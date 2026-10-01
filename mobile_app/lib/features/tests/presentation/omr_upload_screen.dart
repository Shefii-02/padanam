import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/network/api_exception.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/widgets/ui.dart';
import '../data/test_repository.dart';

/// OMR exam: the student writes on paper, then uploads a photo of the sheet. A teacher evaluates it.
class OmrUploadScreen extends ConsumerStatefulWidget {
  const OmrUploadScreen({super.key, required this.testId, this.title});
  final int testId;
  final String? title;

  @override
  ConsumerState<OmrUploadScreen> createState() => _OmrUploadScreenState();
}

class _OmrUploadScreenState extends ConsumerState<OmrUploadScreen> {
  XFile? _photo;
  bool _busy = false, _sent = false;
  double _progress = 0;

  Future<void> _take(ImageSource src) async {
    final x = await ImagePicker().pickImage(source: src, imageQuality: 85, maxWidth: 2400);
    if (x != null) setState(() => _photo = x);
  }

  Future<void> _send() async {
    setState(() {
      _busy = true;
      _progress = 0;
    });
    try {
      await ref.read(testRepositoryProvider).uploadOmr(widget.testId, _photo!.path);
      setState(() => _sent = true);
    } catch (e) {
      if (mounted) showSoon(context, e is ApiException ? e.message : 'Upload failed. Please try again.');
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = context.palette;
    if (_sent) {
      return SubPage(title: widget.title ?? 'OMR exam', children: [
        const SizedBox(height: 40),
        const Center(child: CircleAvatar(radius: 44, backgroundColor: AppColors.mint, child: Icon(Icons.check_rounded, size: 46, color: AppColors.green))),
        const SizedBox(height: 16),
        const Center(child: Text('Sheet submitted', style: TextStyle(fontSize: 21, fontWeight: FontWeight.w800))),
        const SizedBox(height: 8),
        Center(child: Text('Your teacher checks it. You get your score and rank as a notification.', textAlign: TextAlign.center, style: TextStyle(color: p.muted, height: 1.5))),
        const SizedBox(height: 24),
        AppButton('Done', expand: true, onPressed: () => context.pop()),
      ]);
    }
    return SubPage(
      title: widget.title ?? 'OMR exam',
      subtitle: 'Write on paper, then upload your sheet',
      footer: _photo == null
          ? Row(children: [
              Expanded(child: AppButton('Gallery', outlined: true, onPressed: () => _take(ImageSource.gallery))),
              const SizedBox(width: 10),
              Expanded(flex: 2, child: AppButton('Take photo', emoji: '📷', onPressed: () => _take(ImageSource.camera))),
            ])
          : Row(children: [
              Expanded(child: AppButton('Retake', outlined: true, onPressed: _busy ? null : () => setState(() => _photo = null))),
              const SizedBox(width: 10),
              Expanded(flex: 2, child: AppButton(_busy ? 'Uploading…' : 'Submit sheet', onPressed: _busy ? null : _send)),
            ]),
      children: [
        if (_photo == null) ...[
          Container(
            height: 320,
            decoration: BoxDecoration(color: p.card, borderRadius: BorderRadius.circular(18), border: Border.all(color: AppColors.primary2, width: 2)),
            alignment: Alignment.center,
            padding: const EdgeInsets.all(20),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              const Icon(Icons.document_scanner_rounded, size: 48, color: AppColors.primary2),
              const SizedBox(height: 10),
              const Text('Photo of the full OMR sheet', style: TextStyle(fontWeight: FontWeight.w800)),
              const SizedBox(height: 6),
              Text('Good light, no shadow, all 4 corner marks visible. Hold the phone straight above the sheet.', textAlign: TextAlign.center, style: TextStyle(color: p.muted, height: 1.45)),
            ]),
          ),
        ] else ...[
          ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: kIsWeb ? Image.network(_photo!.path, fit: BoxFit.contain) : Image.file(File(_photo!.path), fit: BoxFit.contain),
          ),
          if (_busy) ...[const SizedBox(height: 12), LinearProgressIndicator(value: _progress == 0 ? null : _progress)],
          const SizedBox(height: 10),
          Text('Check that every bubble is clear before submitting. You can upload only once.', style: TextStyle(fontSize: 12.5, color: p.muted)),
        ],
      ],
    );
  }
}
